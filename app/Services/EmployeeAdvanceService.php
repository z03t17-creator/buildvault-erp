<?php

namespace App\Services;

use App\Models\EmployeeAdvance;
use App\Models\Project;
use App\Models\User;
use App\Models\Worker;
use InvalidArgumentException;

class EmployeeAdvanceService
{
    /**
     * @param  array{
     *     worker_id: int,
     *     project_id: int,
     *     amount_iqd: float|int|string,
     *     advanced_on: string,
     *     reason: string,
     *     repayment_method: string,
     *     remaining_iqd?: float|int|string|null,
     *     notes?: string|null,
     * }  $data
     */
    public function create(array $data, ?User $actor = null): EmployeeAdvance
    {
        $worker = Worker::query()->findOrFail($data['worker_id']);
        $project = Project::query()->findOrFail($data['project_id']);
        $amount = round((float) $data['amount_iqd'], 2);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Advance amount must be greater than zero.');
        }

        $remaining = array_key_exists('remaining_iqd', $data) && $data['remaining_iqd'] !== null && $data['remaining_iqd'] !== ''
            ? round((float) $data['remaining_iqd'], 2)
            : $amount;

        if ($remaining < 0 || $remaining > $amount) {
            throw new InvalidArgumentException('Remaining amount must be between 0 and the advance amount.');
        }

        $method = (string) $data['repayment_method'];
        if (! in_array($method, EmployeeAdvance::REPAYMENT_METHODS, true)) {
            throw new InvalidArgumentException('Invalid repayment method.');
        }

        $status = $remaining <= 0
            ? EmployeeAdvance::STATUS_REPAID
            : EmployeeAdvance::STATUS_OPEN;

        return EmployeeAdvance::query()->create([
            'worker_id' => $worker->id,
            'project_id' => $project->id,
            'amount_iqd' => $amount,
            'remaining_iqd' => $remaining,
            'advanced_on' => $data['advanced_on'],
            'reason' => $data['reason'],
            'repayment_method' => $method,
            'notes' => $data['notes'] ?? null,
            'status' => $status,
            'entered_by' => $actor?->id,
        ]);
    }

    /**
     * Apply a repayment against remaining balance (IQD).
     */
    public function repay(EmployeeAdvance $advance, float|int|string $amountIqd): EmployeeAdvance
    {
        if (! $advance->isOpen()) {
            throw new InvalidArgumentException('Only open advances can be repaid.');
        }

        $pay = round((float) $amountIqd, 2);
        if ($pay <= 0) {
            throw new InvalidArgumentException('Repayment amount must be greater than zero.');
        }

        $remaining = round((float) $advance->remaining_iqd, 2);
        if ($pay > $remaining) {
            throw new InvalidArgumentException('Repayment cannot exceed remaining amount.');
        }

        $advance->remaining_iqd = round($remaining - $pay, 2);
        if ((float) $advance->remaining_iqd <= 0) {
            $advance->remaining_iqd = 0;
            $advance->status = EmployeeAdvance::STATUS_REPAID;
        }
        $advance->save();

        return $advance->fresh();
    }

    public function cancel(EmployeeAdvance $advance): EmployeeAdvance
    {
        if ($advance->status === EmployeeAdvance::STATUS_CANCELLED) {
            throw new InvalidArgumentException('Advance is already cancelled.');
        }

        if ($advance->status === EmployeeAdvance::STATUS_REPAID) {
            throw new InvalidArgumentException('Fully repaid advances cannot be cancelled.');
        }

        $advance->status = EmployeeAdvance::STATUS_CANCELLED;
        $advance->remaining_iqd = 0;
        $advance->save();

        return $advance->fresh();
    }

    /**
     * Open payroll-deduction remaining balance for a worker (IQD).
     */
    public function openPayrollRemainingIqd(Worker $worker): float
    {
        return round((float) EmployeeAdvance::query()
            ->where('worker_id', $worker->id)
            ->where('status', EmployeeAdvance::STATUS_OPEN)
            ->where('repayment_method', EmployeeAdvance::REPAY_PAYROLL)
            ->sum('remaining_iqd'), 2);
    }
}
