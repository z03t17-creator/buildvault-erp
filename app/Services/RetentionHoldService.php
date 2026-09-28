<?php

namespace App\Services;

use App\Models\ProjectAllocation;
use App\Models\RetentionHold;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RetentionHoldService
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRates,
    ) {}

    /**
     * Flip holding → matured when maturity_date has been reached.
     *
     * @return Collection<int, RetentionHold>
     */
    public function markDueAsMatured(?Carbon $asOf = null): Collection
    {
        $asOf ??= now()->startOfDay();

        return DB::transaction(function () use ($asOf) {
            $due = RetentionHold::query()
                ->where('status', RetentionHold::STATUS_HOLDING)
                ->whereDate('maturity_date', '<=', $asOf->toDateString())
                ->lockForUpdate()
                ->get();

            foreach ($due as $hold) {
                $hold->status = RetentionHold::STATUS_MATURED;
                $hold->save();
            }

            return RetentionHold::query()
                ->whereIn('id', $due->pluck('id'))
                ->get();
        });
    }

    /**
     * Return matured insurance to the project staff payroll pool.
     */
    public function release(RetentionHold $hold, ?int $releasedBy = null): RetentionHold
    {
        if ($hold->status !== RetentionHold::STATUS_MATURED) {
            throw new InvalidArgumentException('Only matured insurance holds can be released to payroll.');
        }

        return DB::transaction(function () use ($hold, $releasedBy) {
            $hold = RetentionHold::query()->lockForUpdate()->findOrFail($hold->id);

            if ($hold->status !== RetentionHold::STATUS_MATURED) {
                throw new InvalidArgumentException('Only matured insurance holds can be released to payroll.');
            }

            $amount = round((float) $hold->amount_usd, 2);

            $allocation = ProjectAllocation::query()
                ->where('project_id', $hold->project_id)
                ->lockForUpdate()
                ->first();

            if (! $allocation) {
                $allocation = ProjectAllocation::query()->create([
                    'project_id' => $hold->project_id,
                    'expenses_pool_usd' => 0,
                    'payroll_pool_usd' => 0,
                    'retention_pool_usd' => 0,
                    'penalty_pool_usd' => 0,
                    'profit_pool_usd' => 0,
                ]);
                $allocation = ProjectAllocation::query()->lockForUpdate()->findOrFail($allocation->id);
            }

            $allocation->payroll_pool_usd = round((float) $allocation->payroll_pool_usd + $amount, 2);
            $allocation->save();

            $rate = $this->exchangeRates->getUsdToIqd();

            Transaction::query()->create([
                'vault_id' => $hold->vault_id,
                'project_id' => $hold->project_id,
                'type' => Transaction::TYPE_ADJUSTMENT,
                'amount_usd' => $amount,
                'amount_iqd' => round($amount * $rate, 2),
                'exchange_rate' => $rate,
                'description' => sprintf(
                    'Insurance hold #%d released to payroll pool (worker #%d)',
                    $hold->id,
                    $hold->worker_id,
                ),
                'reference_type' => $hold->getMorphClass(),
                'reference_id' => $hold->id,
                'created_by' => $releasedBy,
            ]);

            $hold->status = RetentionHold::STATUS_RELEASED;
            $hold->released_at = now();
            $hold->released_amount_usd = $amount;
            $hold->save();

            return $hold->fresh(['worker', 'project', 'payout']);
        });
    }

    /**
     * Matured holds awaiting release (dashboard / nav alerts).
     *
     * @return Collection<int, RetentionHold>
     */
    public function maturedAwaitingRelease(): Collection
    {
        return RetentionHold::query()
            ->where('status', RetentionHold::STATUS_MATURED)
            ->with(['worker:id,name', 'project:id,name'])
            ->orderBy('maturity_date')
            ->get();
    }

    public function maturedCount(): int
    {
        return RetentionHold::query()
            ->where('status', RetentionHold::STATUS_MATURED)
            ->count();
    }
}
