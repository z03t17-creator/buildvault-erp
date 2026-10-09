<?php

namespace App\Services;

use App\Models\EmployeeAdvance;
use App\Models\Penalty;
use App\Models\StaffStatement;
use App\Models\Worker;
use InvalidArgumentException;

/**
 * Staff unit settlement: Gross − 10% retention − all advances − penalties (per currency).
 * Employee monthly salary has no automatic retention.
 */
class StaffSettlementService
{
    public function __construct(
        private readonly RetentionMathService $math,
    ) {}

    /**
     * Preview settlement for a Staff person from live advances + applied penalties + optional statement earned.
     *
     * @return array{
     *   labor_kind: string,
     *   employee_salary_has_retention: bool,
     *   gross_usd: float,
     *   gross_iqd: float,
     *   retention_usd: float,
     *   retention_iqd: float,
     *   advances_usd: float,
     *   advances_iqd: float,
     *   penalties_usd: float,
     *   penalties_iqd: float,
     *   net_usd: float,
     *   net_iqd: float,
     *   hold_pct: float,
     *   maturity_days: int,
     *   advances: list<array<string, mixed>>,
     *   penalties: list<array<string, mixed>>,
     * }
     */
    public function preview(Worker $worker, ?float $grossUsd = null, ?float $grossIqd = null): array
    {
        if ($worker->isEmployee()) {
            return [
                'labor_kind' => Worker::LABOR_KIND_WORKER,
                'employee_salary_has_retention' => $this->math->employeeSalaryHasRetention(),
                'gross_usd' => round((float) ($worker->monthly_salary_usd ?? 0), 2),
                'gross_iqd' => round((float) ($worker->monthly_salary_iqd ?? 0), 2),
                'retention_usd' => 0.0,
                'retention_iqd' => 0.0,
                'advances_usd' => $this->openAdvancesUsd($worker),
                'advances_iqd' => $this->openAdvancesIqd($worker),
                'penalties_usd' => $this->appliedPenaltiesUsd($worker),
                'penalties_iqd' => $this->appliedPenaltiesIqd($worker),
                'net_usd' => 0.0,
                'net_iqd' => 0.0,
                'hold_pct' => 0.0,
                'maturity_days' => 0,
                'advances' => $this->advanceRows($worker),
                'penalties' => $this->penaltyRows($worker),
                'note' => 'Employee monthly salary has no automatic 10% retention. Net = salary − advances − penalties (payroll engine).',
            ];
        }

        if (! $worker->isStaff()) {
            throw new InvalidArgumentException('Classify person as Staff before unit settlement.');
        }

        $grossUsd = round((float) ($grossUsd ?? $this->openStatementsEarnedUsd($worker)), 2);
        $grossIqd = round((float) ($grossIqd ?? $this->openStatementsEarnedIqd($worker)), 2);

        $retention = $this->math->staffWorkPayRetention($grossUsd, $grossIqd);
        $advUsd = $this->openAdvancesUsd($worker);
        $advIqd = $this->openAdvancesIqd($worker);
        $penUsd = $this->appliedPenaltiesUsd($worker);
        $penIqd = $this->appliedPenaltiesIqd($worker);

        $net = $this->math->staffNetPayable(
            $grossUsd,
            $grossIqd,
            $retention['retention_usd'],
            $retention['retention_iqd'],
            $advUsd,
            $advIqd,
            $penUsd,
            $penIqd,
        );

        return [
            'labor_kind' => Worker::LABOR_KIND_STAFF,
            'employee_salary_has_retention' => false,
            ...$net,
            'hold_pct' => $retention['hold_pct'],
            'maturity_days' => $retention['maturity_days'],
            'maturity_date' => $retention['maturity_date'],
            'advances' => $this->advanceRows($worker),
            'penalties' => $this->penaltyRows($worker),
            'note' => 'Gross − 10% retention − all advances − penalties = Net still to pay (per currency).',
        ];
    }

    /**
     * Apply RetentionMath into an open StaffStatement (refresh advances/penalties/retention/remaining).
     */
    public function refreshStatement(StaffStatement $statement): StaffStatement
    {
        $worker = $statement->worker;
        if (! $worker || ! $worker->isStaff()) {
            throw new InvalidArgumentException('Staff statement requires a Staff person.');
        }

        $grossUsd = round((float) $statement->earned_usd, 2);
        $grossIqd = round((float) $statement->earned_iqd, 2);
        $retention = $this->math->staffWorkPayRetention($grossUsd, $grossIqd);

        $statement->retention_held_usd = $retention['retention_usd'];
        $statement->retention_held_iqd = $retention['retention_iqd'];
        $statement->advances_usd = $this->openAdvancesUsd($worker);
        $statement->advances_iqd = $this->openAdvancesIqd($worker);
        $statement->penalties_usd = $this->appliedPenaltiesUsd($worker);
        $statement->penalties_iqd = $this->appliedPenaltiesIqd($worker);
        $statement->recalculateRemaining();
        $statement->save();

        return $statement->fresh();
    }

    private function openAdvancesUsd(Worker $worker): float
    {
        return round((float) EmployeeAdvance::query()
            ->where('worker_id', $worker->id)
            ->where('status', EmployeeAdvance::STATUS_OPEN)
            ->sum('remaining_usd'), 2);
    }

    private function openAdvancesIqd(Worker $worker): float
    {
        return round((float) EmployeeAdvance::query()
            ->where('worker_id', $worker->id)
            ->where('status', EmployeeAdvance::STATUS_OPEN)
            ->sum('remaining_iqd'), 2);
    }

    private function appliedPenaltiesUsd(Worker $worker): float
    {
        return round((float) Penalty::query()
            ->where('worker_id', $worker->id)
            ->where('status', Penalty::STATUS_APPLIED)
            ->sum('amount_usd'), 2);
    }

    private function appliedPenaltiesIqd(Worker $worker): float
    {
        return round((float) Penalty::query()
            ->where('worker_id', $worker->id)
            ->where('status', Penalty::STATUS_APPLIED)
            ->sum('amount_iqd'), 2);
    }

    private function openStatementsEarnedUsd(Worker $worker): float
    {
        return round((float) StaffStatement::query()
            ->where('worker_id', $worker->id)
            ->where('status', StaffStatement::STATUS_OPEN)
            ->sum('earned_usd'), 2);
    }

    private function openStatementsEarnedIqd(Worker $worker): float
    {
        return round((float) StaffStatement::query()
            ->where('worker_id', $worker->id)
            ->where('status', StaffStatement::STATUS_OPEN)
            ->sum('earned_iqd'), 2);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function advanceRows(Worker $worker): array
    {
        return EmployeeAdvance::query()
            ->where('worker_id', $worker->id)
            ->where('status', EmployeeAdvance::STATUS_OPEN)
            ->orderByDesc('advanced_on')
            ->get()
            ->map(fn (EmployeeAdvance $a) => [
                'id' => $a->id,
                'advanced_on' => optional($a->advanced_on)->toDateString() ?? $a->advanced_on,
                'currency' => $a->currency,
                'remaining_usd' => (float) ($a->remaining_usd ?? 0),
                'remaining_iqd' => (float) ($a->remaining_iqd ?? 0),
                'amount_usd' => (float) ($a->amount_usd ?? 0),
                'amount_iqd' => (float) ($a->amount_iqd ?? 0),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function penaltyRows(Worker $worker): array
    {
        return Penalty::query()
            ->where('worker_id', $worker->id)
            ->where('status', Penalty::STATUS_APPLIED)
            ->orderByDesc('occurred_on')
            ->get()
            ->map(fn (Penalty $p) => [
                'id' => $p->id,
                'type' => $p->type,
                'reason' => $p->reason,
                'amount_usd' => (float) $p->amount_usd,
                'amount_iqd' => (float) $p->amount_iqd,
                'occurred_on' => optional($p->occurred_on)->toDateString() ?? $p->occurred_on,
            ])
            ->all();
    }
}
