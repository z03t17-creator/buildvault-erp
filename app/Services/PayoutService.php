<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\Project;
use App\Models\ProjectAllocation;
use App\Models\RetentionHold;
use App\Models\Transaction;
use App\Models\Vault;
use Database\Seeders\VaultSeeder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PayoutService
{
    public function __construct(
        private readonly LiquidityService $liquidity,
        private readonly ExchangeRateService $exchangeRates,
    ) {}

    /**
     * Create a pending payout after ability-to-pay check.
     *
     * @param  array{
     *     project_id: int,
     *     category: string,
     *     amount_usd: float|int|string,
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

        $amountUsd = round((float) $data['amount_usd'], 2);
        $category = (string) $data['category'];

        $this->liquidity->assertCanPay($project, $category, $amountUsd, $vault);

        $holdback = array_key_exists('retention_holdback', $data) && $data['retention_holdback'] !== null
            ? round((float) $data['retention_holdback'], 2)
            : $this->defaultHoldback($project, $category, $amountUsd, $data['worker_id'] ?? null);

        if ($holdback < 0 || $holdback > $amountUsd) {
            throw new InvalidArgumentException('Retention holdback must be between 0 and the payout amount.');
        }

        $rate = $this->exchangeRates->getUsdToIqd();

        return Payout::query()->create([
            'vault_id' => $vault->id,
            'project_id' => $project->id,
            'worker_id' => $data['worker_id'] ?? null,
            'floor_id' => $data['floor_id'] ?? null,
            'category' => $category,
            'amount_usd' => $amountUsd,
            'amount_iqd' => round($amountUsd * $rate, 2),
            'exchange_rate' => $rate,
            'retention_holdback' => $holdback,
            'status' => Payout::STATUS_PENDING,
            'notes' => $data['notes'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    /**
     * Approve pending payout: deduct vault/pool, ledger withdrawal, optional retention hold.
     */
    public function approve(Payout $payout): Payout
    {
        if ($payout->status !== Payout::STATUS_PENDING) {
            throw new InvalidArgumentException('Only pending payouts can be approved.');
        }

        return DB::transaction(function () use ($payout) {
            $payout = Payout::query()->lockForUpdate()->findOrFail($payout->id);
            $vault = Vault::query()->lockForUpdate()->findOrFail($payout->vault_id);
            $project = Project::query()->findOrFail($payout->project_id);

            $amount = round((float) $payout->amount_usd, 2);
            $holdback = round((float) $payout->retention_holdback, 2);
            $cashOut = round($amount - $holdback, 2);

            // Re-check pool (pending already reserved in available formula).
            $pool = $this->liquidity->poolAvailableUsd($project, $payout->category);
            if ($amount > $pool) {
                throw new InvalidArgumentException(sprintf(
                    'Cannot approve: %s pool has %.2f USD but payout needs %.2f USD.',
                    $payout->category,
                    $pool,
                    $amount,
                ));
            }

            if ($cashOut > (float) $vault->balance_usd) {
                throw new InvalidArgumentException('Cannot approve: vault cash balance insufficient for net payout.');
            }

            $column = LiquidityService::CATEGORY_POOL_COLUMNS[$payout->category];
            $allocation = ProjectAllocation::query()
                ->where('project_id', $project->id)
                ->lockForUpdate()
                ->firstOrFail();

            $allocation->{$column} = round((float) $allocation->{$column} - $amount, 2);
            $allocation->save();

            $rate = (float) $payout->exchange_rate ?: $this->exchangeRates->getUsdToIqd();
            $cashOutIqd = round($cashOut * $rate, 2);

            $vault->balance_usd = round((float) $vault->balance_usd - $cashOut, 2);
            $vault->balance_iqd = round((float) $vault->balance_iqd - $cashOutIqd, 2);
            $vault->save();

            $payout->status = Payout::STATUS_APPROVED;
            $payout->approved_at = now();
            $payout->save();

            Transaction::query()->create([
                'vault_id' => $vault->id,
                'project_id' => $project->id,
                'type' => Transaction::TYPE_WITHDRAWAL,
                'amount_usd' => $cashOut,
                'amount_iqd' => $cashOutIqd,
                'exchange_rate' => $rate,
                'description' => sprintf('Payout #%d approved (%s)', $payout->id, $payout->category),
                'reference_type' => $payout->getMorphClass(),
                'reference_id' => $payout->id,
                'created_by' => $payout->created_by,
            ]);

            if ($this->shouldCreateRetentionHold($payout, $holdback)) {
                $holdStart = now()->toDateString();
                RetentionHold::query()->create([
                    'vault_id' => $vault->id,
                    'project_id' => $project->id,
                    'worker_id' => $payout->worker_id,
                    'payout_id' => $payout->id,
                    'amount_usd' => $holdback,
                    'hold_start' => $holdStart,
                    'maturity_date' => RetentionHold::maturityFrom($holdStart)->toDateString(),
                    'status' => RetentionHold::STATUS_HOLDING,
                ]);
            }

            return $payout->fresh(['retentionHolds', 'project', 'worker']);
        });
    }

    public function reject(Payout $payout, ?string $notes = null): Payout
    {
        if ($payout->status !== Payout::STATUS_PENDING) {
            throw new InvalidArgumentException('Only pending payouts can be rejected.');
        }

        $payout->status = Payout::STATUS_REJECTED;
        if ($notes !== null) {
            $payout->notes = trim(($payout->notes ? $payout->notes."\n" : '').$notes);
        }
        $payout->save();

        return $payout->fresh();
    }

    public function reconcile(Payout $payout): Payout
    {
        if ($payout->status !== Payout::STATUS_APPROVED) {
            throw new InvalidArgumentException('Only approved payouts can be reconciled.');
        }

        $payout->status = Payout::STATUS_RECONCILED;
        $payout->reconciled_at = now();
        $payout->save();

        return $payout->fresh();
    }

    /**
     * Default holdback: insurance % of payroll payouts with a worker (shared 10% reserve).
     */
    protected function defaultHoldback(Project $project, string $category, float $amountUsd, mixed $workerId): float
    {
        if ($category !== Payout::CATEGORY_PAYROLL || empty($workerId)) {
            return 0.0;
        }

        $pct = (float) $project->allocation_insurance_pct;

        return round($amountUsd * ($pct / 100), 2);
    }

    protected function shouldCreateRetentionHold(Payout $payout, float $holdback): bool
    {
        return $holdback > 0
            && $payout->worker_id !== null
            && in_array($payout->category, [Payout::CATEGORY_PAYROLL, Payout::CATEGORY_RETENTION], true);
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
