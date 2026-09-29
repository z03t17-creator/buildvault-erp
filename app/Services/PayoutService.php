<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\Project;
use App\Models\ProjectAllocation;
use App\Models\RetentionHold;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vault;
use App\Support\AuditActions;
use App\Support\DualCurrency;
use Database\Seeders\VaultSeeder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PayoutService
{
    public function __construct(
        private readonly LiquidityService $liquidity,
        private readonly VaultBalanceService $balances,
        private readonly PenaltyService $penalties,
        private readonly AuditLogger $audit,
        private readonly InsuranceSettings $insurance,
    ) {}

    /**
     * Create a pending payout after ability-to-pay check (Qasa single-leg).
     *
     * @param  array{
     *     project_id: int,
     *     category: string,
     *     amount?: float|int|string,
     *     amount_usd?: float|int|string,
     *     amount_iqd?: float|int|string,
     *     currency?: string,
     *     worker_id?: int|null,
     *     floor_id?: int|null,
     *     retention_holdback?: float|int|string|null,
     *     notes?: string|null,
     *     vault_id?: int|null,
     *     created_by?: int|null,
     * }  $data
     */
    public function create(array $data): Payout
    {
        $project = Project::query()->findOrFail($data['project_id']);
        $vault = isset($data['vault_id'])
            ? Vault::query()->findOrFail($data['vault_id'])
            : $this->zhakoVault();

        $currency = strtoupper((string) ($data['currency'] ?? DualCurrency::USD));
        $amount = $data['amount']
            ?? ($currency === DualCurrency::USD ? ($data['amount_usd'] ?? null) : ($data['amount_iqd'] ?? null));
        $legs = DualCurrency::legs($currency, $amount);
        $category = (string) $data['category'];

        $this->liquidity->assertCanPayCurrency(
            $project,
            $category,
            $legs['currency'],
            DualCurrency::primaryAmount($legs),
            $vault,
        );

        $primary = DualCurrency::primaryAmount($legs);
        $holdback = array_key_exists('retention_holdback', $data) && $data['retention_holdback'] !== null
            ? round((float) $data['retention_holdback'], 2)
            : ($legs['currency'] === DualCurrency::USD
                ? $this->defaultHoldback($project, $category, $primary, $data['worker_id'] ?? null)
                : 0.0);

        if ($holdback < 0 || $holdback > $primary) {
            throw new InvalidArgumentException('Retention holdback must be between 0 and the payout amount.');
        }

        return Payout::query()->create([
            'vault_id' => $vault->id,
            'project_id' => $project->id,
            'worker_id' => $data['worker_id'] ?? null,
            'floor_id' => $data['floor_id'] ?? null,
            'category' => $category,
            'amount_usd' => $legs['amount_usd'],
            'amount_iqd' => $legs['amount_iqd'],
            'exchange_rate' => $legs['exchange_rate'],
            'currency' => $legs['currency'],
            'retention_holdback' => $holdback,
            'status' => Payout::STATUS_PENDING,
            'notes' => $data['notes'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    public function approve(Payout $payout, ?User $approver = null): Payout
    {
        if (! $payout->isAwaitingPayAbility()) {
            throw new InvalidArgumentException('Only pending or held payouts can be approved.');
        }

        return DB::transaction(function () use ($payout, $approver) {
            $payout = Payout::query()->lockForUpdate()->findOrFail($payout->id);
            $vault = Vault::query()->lockForUpdate()->findOrFail($payout->vault_id);
            $project = Project::query()->findOrFail($payout->project_id);

            $currency = strtoupper((string) ($payout->currency ?: (
                (float) $payout->amount_usd > 0 ? DualCurrency::USD : DualCurrency::IQD
            )));
            $amount = $currency === DualCurrency::USD
                ? round((float) $payout->amount_usd, 2)
                : round((float) $payout->amount_iqd, 2);
            $holdback = round((float) $payout->retention_holdback, 2);
            $cashOut = round($amount - $holdback, 2);

            $this->liquidity->assertCanPayCurrency(
                $project,
                $payout->category,
                $currency,
                $amount,
                $vault,
                excludePayoutId: $payout->id,
            );

            if ($currency === DualCurrency::USD) {
                $column = LiquidityService::CATEGORY_POOL_COLUMNS[$payout->category];
                $allocation = ProjectAllocation::query()
                    ->where('project_id', $project->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $poolBefore = (float) $allocation->{$column};
                $allocation->{$column} = round($poolBefore - $amount, 2);
                $allocation->save();

                $this->audit->log(
                    AuditActions::ALLOCATION_CHANGED,
                    sprintf('Allocation pool %s reduced by %.2f USD (payout #%d)', $column, $amount, $payout->id),
                    $allocation,
                    [
                        'project_id' => $project->id,
                        'pool' => $column,
                        'before' => $poolBefore,
                        'after' => (float) $allocation->{$column},
                        'delta_usd' => -$amount,
                        'reason' => 'payout_approve',
                        'payout_id' => $payout->id,
                    ],
                    $approver,
                );
            }

            $cashLegs = DualCurrency::legs($currency, max($cashOut, 0.01));
            if ($cashOut <= 0) {
                $cashLegs = [
                    'currency' => $currency,
                    'amount_usd' => 0.0,
                    'amount_iqd' => 0.0,
                    'exchange_rate' => 0.0,
                ];
            } else {
                $cashLegs = DualCurrency::legs($currency, $cashOut);
            }

            $payout->status = Payout::STATUS_APPROVED;
            $payout->approved_at = now();
            $payout->save();

            if ($cashOut > 0) {
                $txn = Transaction::query()->create([
                    'vault_id' => $vault->id,
                    'project_id' => $project->id,
                    'type' => Transaction::typeForPayoutCategory($payout->category),
                    'direction' => 'out',
                    'occurred_on' => now()->toDateString(),
                    'amount_usd' => $cashLegs['amount_usd'],
                    'amount_iqd' => $cashLegs['amount_iqd'],
                    'exchange_rate' => 0,
                    'description' => sprintf('Payout #%d approved (%s)', $payout->id, $payout->category),
                    'reference_code' => 'PAY-'.$payout->id,
                    'reference_type' => $payout->getMorphClass(),
                    'reference_id' => $payout->id,
                    'created_by' => $approver?->id ?? $payout->created_by,
                ]);
                $this->balances->apply($txn, $vault);
            }

            if ($this->shouldCreateRetentionHold($payout, $holdback) && $currency === DualCurrency::USD) {
                $holdStart = now()->toDateString();
                $holdPct = $amount > 0
                    ? round(($holdback / $amount) * 100, 2)
                    : $this->insurance->holdbackPercent();

                RetentionHold::query()->create([
                    'vault_id' => $vault->id,
                    'project_id' => $project->id,
                    'worker_id' => $payout->worker_id,
                    'payout_id' => $payout->id,
                    'pay_period' => now()->format('Y-m'),
                    'hold_pct' => $holdPct,
                    'amount_usd' => $holdback,
                    'amount_iqd' => 0,
                    'hold_start' => $holdStart,
                    'maturity_date' => RetentionHold::maturityFrom($holdStart)->toDateString(),
                    'status' => RetentionHold::STATUS_HOLDING,
                    'layer' => 'staff',
                    'maturity_days' => 180,
                ]);
            }

            $this->audit->log(
                AuditActions::PAYOUT_APPROVED,
                sprintf('Payout #%d approved (%s, %.2f %s)', $payout->id, $payout->category, $amount, $currency),
                $payout,
                [
                    'payout_id' => $payout->id,
                    'project_id' => $project->id,
                    'category' => $payout->category,
                    'currency' => $currency,
                    'amount_usd' => (float) $payout->amount_usd,
                    'amount_iqd' => (float) $payout->amount_iqd,
                    'cash_out' => $cashOut,
                    'retention_holdback' => $holdback,
                ],
                $approver,
            );

            return $payout->fresh(['retentionHolds', 'project', 'worker']);
        });
    }

    public function hold(Payout $payout, ?string $notes = null, ?User $actor = null): Payout
    {
        if (! $payout->isAwaitingPayAbility()) {
            throw new InvalidArgumentException('Only pending or held payouts can be held.');
        }

        $payout->status = Payout::STATUS_HELD;
        $payout->held_at = now();
        $payout->held_by = $actor?->id;
        $payout->pay_ability_notes = $notes;
        $payout->save();

        $this->audit->log(
            AuditActions::PAYOUT_HELD,
            sprintf('Payout #%d held (ability to pay)', $payout->id),
            $payout,
            ['payout_id' => $payout->id, 'notes' => $notes],
            $actor,
        );

        return $payout->fresh();
    }

    public function reject(Payout $payout, ?string $notes = null, ?User $actor = null): Payout
    {
        if (! $payout->isAwaitingPayAbility()) {
            throw new InvalidArgumentException('Only pending or held payouts can be rejected.');
        }

        $payout->status = Payout::STATUS_REJECTED;
        $payout->pay_ability_notes = $notes;
        if ($notes !== null) {
            $payout->notes = trim(($payout->notes ? $payout->notes."\n" : '').$notes);
        }
        $payout->save();

        $this->audit->log(
            AuditActions::PAYOUT_REJECTED,
            sprintf('Payout #%d rejected', $payout->id),
            $payout,
            [
                'payout_id' => $payout->id,
                'project_id' => $payout->project_id,
                'amount_usd' => (float) $payout->amount_usd,
                'amount_iqd' => (float) $payout->amount_iqd,
                'notes' => $notes,
            ],
            $actor,
        );

        return $payout->fresh();
    }

    public function reconcile(Payout $payout): Payout
    {
        if ($payout->status !== Payout::STATUS_APPROVED) {
            throw new InvalidArgumentException('Only approved payouts can be reconciled.');
        }

        return DB::transaction(function () use ($payout) {
            $payout = Payout::query()->lockForUpdate()->findOrFail($payout->id);

            if ($payout->status !== Payout::STATUS_APPROVED) {
                throw new InvalidArgumentException('Only approved payouts can be reconciled.');
            }

            $this->penalties->applyDeductionsForPayout($payout);

            $payout->status = Payout::STATUS_RECONCILED;
            $payout->reconciled_at = now();
            $payout->save();

            return $payout->fresh(['penalties', 'retentionHolds']);
        });
    }

    protected function defaultHoldback(Project $project, string $category, float $amountUsd, mixed $workerId): float
    {
        if ($category !== Payout::CATEGORY_PAYROLL || ! $workerId) {
            return 0.0;
        }

        return round($amountUsd * ($this->insurance->holdbackPercent() / 100.0), 2);
    }

    protected function shouldCreateRetentionHold(Payout $payout, float $holdback): bool
    {
        return $holdback > 0
            && $payout->category === Payout::CATEGORY_PAYROLL
            && $payout->worker_id;
    }

    protected function zhakoVault(): Vault
    {
        $vault = Vault::query()->where('name', VaultSeeder::NAME)->first()
            ?? Vault::query()->orderBy('id')->first();

        if (! $vault) {
            throw new InvalidArgumentException('No vault found. Seed the Zhako vault first.');
        }

        return $vault;
    }
}
