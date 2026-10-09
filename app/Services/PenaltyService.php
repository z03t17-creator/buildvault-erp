<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\Penalty;
use App\Models\Project;
use App\Models\ProjectAllocation;
use App\Models\Transaction;
use App\Models\Vault;
use App\Models\Staff;
use App\Support\DualCurrency;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PenaltyService
{
    public function __construct() {}

    /**
     * Record a penalty against staff (optionally link to a payout for reconcile deduction).
     *
     * Accepts amount_iqd or amount_usd with a currency selector — unused side stays 0.
     *
     * @param  array{
     *     staff_id: int,
     *     project_id: int,
     *     reason: string,
     *     amount_usd?: float|int|string|null,
     *     amount_iqd?: float|int|string|null,
     *     type?: string|null,
     *     occurred_on?: string|null,
     *     notes?: string|null,
     *     floor_id?: int|null,
     *     payout_id?: int|null,
     *     created_by?: int|null,
     *     status?: string|null,
     *     currency?: string|null,
     * }  $data
     */
    public function create(array $data): Penalty
    {
        $staff = Staff::query()->findOrFail($data['staff_id']);
        $project = Project::query()->findOrFail($data['project_id']);

        [$amountUsd, $amountIqd] = $this->resolveAmounts($data);

        if ($amountUsd <= 0 && $amountIqd <= 0) {
            throw new InvalidArgumentException('Fine Amount must be greater than zero.');
        }

        $type = $data['type'] ?? Penalty::TYPE_OTHER;
        if (! in_array($type, Penalty::TYPES, true)) {
            throw new InvalidArgumentException('Invalid penalty type.');
        }

        $payoutId = $data['payout_id'] ?? null;
        if ($payoutId !== null && $payoutId !== '') {
            $payout = Payout::query()->findOrFail($payoutId);
            if (! in_array($payout->status, [Payout::STATUS_PENDING, Payout::STATUS_APPROVED], true)) {
                throw new InvalidArgumentException('Penalties can only link to pending or approved payouts.');
            }
        } else {
            $payoutId = null;
        }

        $status = $data['status'] ?? Penalty::STATUS_PENDING;
        if (! in_array($status, Penalty::STATUSES, true)) {
            throw new InvalidArgumentException('Invalid penalty status.');
        }

        $currency = isset($data['currency'])
            ? strtoupper((string) $data['currency'])
            : ($amountUsd > 0 ? DualCurrency::USD : DualCurrency::IQD);

        return Penalty::query()->create([
            'staff_id' => $staff->id,
            'project_id' => $project->id,
            'floor_id' => $data['floor_id'] ?? null,
            'type' => $type,
            'reason' => $data['reason'],
            'notes' => $data['notes'] ?? null,
            'amount_usd' => $amountUsd,
            'amount_iqd' => $amountIqd,
            'currency' => $currency,
            'occurred_on' => $data['occurred_on'] ?? now()->toDateString(),
            'payout_id' => $payoutId,
            'deducted_from_payout' => false,
            'status' => $status,
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    /**
     * Mark a pending penalty as applied (counts in payroll; does not require payout).
     */
    public function apply(Penalty $penalty): Penalty
    {
        if ($penalty->status !== Penalty::STATUS_PENDING) {
            throw new InvalidArgumentException('Only pending penalties can be applied.');
        }

        $penalty->status = Penalty::STATUS_APPLIED;
        $penalty->save();

        return $penalty->fresh();
    }

    public function waive(Penalty $penalty, ?string $note = null): Penalty
    {
        if ($penalty->status !== Penalty::STATUS_PENDING) {
            throw new InvalidArgumentException('Only pending penalties can be waived.');
        }

        $penalty->status = Penalty::STATUS_WAIVED;
        if ($note) {
            $penalty->reason = trim($penalty->reason.' [waived: '.$note.']');
        }
        $penalty->save();

        return $penalty->fresh();
    }

    /**
     * Attach a pending penalty to a payout so it deducts on reconcile.
     */
    public function linkToPayout(Penalty $penalty, Payout $payout): Penalty
    {
        if ($penalty->status !== Penalty::STATUS_PENDING) {
            throw new InvalidArgumentException('Only pending penalties can be linked.');
        }

        if ($payout->worker_id === null) {
            throw new InvalidArgumentException('Payout must identify a person to link a penalty.');
        }

        if (! in_array($payout->status, [Payout::STATUS_PENDING, Payout::STATUS_APPROVED], true)) {
            throw new InvalidArgumentException('Can only link to pending or approved payouts.');
        }

        $penalty->payout_id = $payout->id;
        $penalty->save();

        return $penalty->fresh();
    }

    /**
     * Apply pending penalties linked to this payout (called on reconcile).
     * Credits the project penalty pool and marks deducted_from_payout.
     *
     * @return Collection<int, Penalty>
     */
    public function applyDeductionsForPayout(Payout $payout): Collection
    {
        return DB::transaction(function () use ($payout) {
            $penalties = Penalty::query()
                ->where('payout_id', $payout->id)
                ->where('status', Penalty::STATUS_PENDING)
                ->lockForUpdate()
                ->get();

            if ($penalties->isEmpty()) {
                return $penalties;
            }

            $total = round((float) $penalties->sum('amount_usd'), 2);
            $allocation = ProjectAllocation::query()
                ->where('project_id', $payout->project_id)
                ->lockForUpdate()
                ->first();

            if ($allocation) {
                $allocation->penalty_pool_usd = round((float) $allocation->penalty_pool_usd + $total, 2);
                $allocation->save();
            }

            $vault = Vault::query()->find($payout->vault_id);
            if ($vault && $total > 0) {
                Transaction::query()->create([
                    'vault_id' => $vault->id,
                    'project_id' => $payout->project_id,
                    'type' => Transaction::TYPE_PENALTY,
                    'occurred_on' => now()->toDateString(),
                    'amount_usd' => $total,
                    'amount_iqd' => 0,
                    'exchange_rate' => 0,
                    'description' => sprintf(
                        'Penalty deductions on payout #%d reconcile (%d item(s))',
                        $payout->id,
                        $penalties->count(),
                    ),
                    'reference_code' => 'PEN-PAY-'.$payout->id,
                    'reference_type' => $payout->getMorphClass(),
                    'reference_id' => $payout->id,
                    'created_by' => $payout->created_by,
                ]);
            }

            foreach ($penalties as $penalty) {
                $penalty->status = Penalty::STATUS_APPLIED;
                $penalty->deducted_from_payout = true;
                $penalty->save();
            }

            return Penalty::query()
                ->whereIn('id', $penalties->pluck('id'))
                ->get();
        });
    }

    /**
     * @param  array{amount_usd?: mixed, amount_iqd?: mixed}  $data
     * @return array{0: float, 1: float}
     */
    protected function resolveAmounts(array $data): array
    {
        $currency = isset($data['currency']) ? strtoupper((string) $data['currency']) : null;
        $hasIqd = array_key_exists('amount_iqd', $data) && $data['amount_iqd'] !== null && $data['amount_iqd'] !== '';
        $hasUsd = array_key_exists('amount_usd', $data) && $data['amount_usd'] !== null && $data['amount_usd'] !== '';

        if ($currency === DualCurrency::USD || ($hasUsd && ! $hasIqd)) {
            $legs = DualCurrency::legs(DualCurrency::USD, $data['amount_usd'] ?? $data['amount'] ?? 0);

            return [$legs['amount_usd'], $legs['amount_iqd']];
        }

        if ($currency === DualCurrency::IQD || ($hasIqd && ! $hasUsd)) {
            $legs = DualCurrency::legs(DualCurrency::IQD, $data['amount_iqd'] ?? $data['amount'] ?? 0);

            return [$legs['amount_usd'], $legs['amount_iqd']];
        }

        throw new InvalidArgumentException('Fine Amount (IQD or USD) is required — pick one currency.');
    }
}
