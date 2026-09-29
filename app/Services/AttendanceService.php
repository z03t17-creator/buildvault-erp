<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Penalty;
use App\Models\User;
use App\Models\Worker;
use App\Support\AuditActions;
use App\Support\DualCurrency;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Worker (employee) attendance. Stock Manager is the primary writer.
 * Late > 30 minutes after shift start → forfeit_day + FORFEIT_DAY penalty (full day cut).
 */
class AttendanceService
{
    public function __construct(
        private readonly PenaltyService $penalties,
        private readonly AuditLogger $audit,
    ) {}

    public function computeLateMinutes(
        ?string $checkIn,
        ?CarbonInterface $date = null,
        ?string $shiftStart = null,
        ?int $graceMinutes = null,
    ): int {
        if ($checkIn === null || $checkIn === '') {
            return 0;
        }

        $date = $date ? Carbon::parse($date)->startOfDay() : now()->startOfDay();
        $shiftStart = $shiftStart ?? (string) config('attendance.shift_start', '08:00');
        $graceMinutes = $graceMinutes ?? (int) config('attendance.late_grace_minutes', 0);

        $checkInAt = $this->combineDateAndTime($date, $checkIn);
        $allowedAt = $this->combineDateAndTime($date, $shiftStart)->addMinutes($graceMinutes);

        if ($checkInAt->lessThanOrEqualTo($allowedAt)) {
            return 0;
        }

        return (int) $allowedAt->diffInMinutes($checkInAt);
    }

    public function computeOvertimeHours(
        ?string $checkOut,
        ?CarbonInterface $date = null,
        ?string $shiftEnd = null,
    ): float {
        if ($checkOut === null || $checkOut === '') {
            return 0.0;
        }

        $date = $date ? Carbon::parse($date)->startOfDay() : now()->startOfDay();
        $shiftEnd = $shiftEnd ?? (string) config('attendance.shift_end', '17:00');

        $checkOutAt = $this->combineDateAndTime($date, $checkOut);
        $endAt = $this->combineDateAndTime($date, $shiftEnd);

        if ($checkOutAt->lessThanOrEqualTo($endAt)) {
            return 0.0;
        }

        $minutes = (int) $endAt->diffInMinutes($checkOutAt);

        return round($minutes / 60, 2);
    }

    public function resolveWorkedStatus(int $lateMinutes): string
    {
        return $lateMinutes > 0
            ? Attendance::STATUS_LATE
            : Attendance::STATUS_PRESENT;
    }

    /**
     * Recalculate late / OT / status. Does not create penalties (see applyForfeitIfNeeded).
     */
    public function recalculate(Attendance $attendance): Attendance
    {
        if ($attendance->isLeave()) {
            $attendance->forfeit_day = false;

            return $attendance;
        }

        if ($attendance->status === Attendance::STATUS_ABSENT_UNEXCUSED
            && ($attendance->check_in === null || $attendance->check_in === '')) {
            $attendance->late_minutes = 0;
            $attendance->overtime_hours = 0;
            $attendance->forfeit_day = false;

            return $attendance;
        }

        $shiftStart = $attendance->shift_start
            ? substr((string) $attendance->shift_start, 0, 5)
            : (string) config('attendance.shift_start', '08:00');

        $late = $this->computeLateMinutes(
            $attendance->check_in,
            $attendance->date,
            $shiftStart,
        );
        $ot = $this->computeOvertimeHours(
            $attendance->check_out,
            $attendance->date,
        );

        $attendance->late_minutes = $late;
        $attendance->overtime_hours = $ot;

        if ($attendance->check_in !== null && $attendance->check_in !== '') {
            $attendance->status = $this->resolveWorkedStatus($late);
        }

        $attendance->forfeit_day = $attendance->shouldForfeitDay();

        return $attendance;
    }

    /**
     * Persist attendance for an employee (labor_kind=worker). Applies late forfeit when due.
     *
     * @param  array{
     *   floor_id?: int|null,
     *   project_id?: int|null,
     *   check_in?: string|null,
     *   check_out?: string|null,
     *   shift_start?: string|null,
     *   status?: string|null,
     *   notes?: string|null,
     *   entered_by?: int|null
     * }  $attributes
     */
    public function record(
        Worker $worker,
        CarbonInterface|string $date,
        array $attributes = [],
        ?User $actor = null,
    ): Attendance {
        if (! $worker->isEmployee()) {
            throw new InvalidArgumentException('Attendance is only for Workers (employees), not Staff.');
        }

        return DB::transaction(function () use ($worker, $date, $attributes, $actor) {
            $day = Carbon::parse($date)->toDateString();
            $attendance = $this->findOrMake($worker->id, $day);

            if (array_key_exists('floor_id', $attributes)) {
                $attendance->floor_id = $attributes['floor_id'];
            }
            if (array_key_exists('project_id', $attributes)) {
                $attendance->project_id = $attributes['project_id'] ?? $worker->project_id;
            } elseif (! $attendance->project_id) {
                $attendance->project_id = $worker->project_id;
            }
            if (array_key_exists('check_in', $attributes)) {
                $attendance->check_in = $attributes['check_in'];
            }
            if (array_key_exists('check_out', $attributes)) {
                $attendance->check_out = $attributes['check_out'];
            }
            if (array_key_exists('shift_start', $attributes) && $attributes['shift_start']) {
                $attendance->shift_start = $attributes['shift_start'];
            } elseif (! $attendance->shift_start) {
                $attendance->shift_start = (string) config('attendance.shift_start', '08:00');
            }
            if (array_key_exists('notes', $attributes)) {
                $attendance->notes = $attributes['notes'];
            }
            if (! empty($attributes['status']) && in_array($attributes['status'], Attendance::STATUSES, true)) {
                $attendance->status = $attributes['status'];
            }
            if (! empty($attributes['entered_by'])) {
                $attendance->entered_by = $attributes['entered_by'];
            } elseif ($actor) {
                $attendance->entered_by = $actor->id;
            }

            // Explicit leave / absent without check-in: skip late math
            if (in_array($attendance->status, [
                Attendance::STATUS_LEAVE_PAID,
                Attendance::STATUS_LEAVE_SICK,
                Attendance::STATUS_ABSENT_UNEXCUSED,
            ], true) && (empty($attributes['check_in']) || $attributes['check_in'] === null)) {
                if ($attendance->status !== Attendance::STATUS_ABSENT_UNEXCUSED
                    || empty($attributes['check_in'])) {
                    $attendance->late_minutes = 0;
                    $attendance->overtime_hours = 0;
                    $attendance->forfeit_day = false;
                    if (in_array($attendance->status, [Attendance::STATUS_LEAVE_PAID, Attendance::STATUS_LEAVE_SICK], true)) {
                        $attendance->check_in = $attendance->check_in ?: null;
                    }
                }
            } else {
                $this->recalculate($attendance);
            }

            $attendance->save();
            $this->applyForfeitIfNeeded($attendance->fresh(['worker', 'penalty']), $actor);

            $this->audit->log(
                AuditActions::ATTENDANCE_RECORDED,
                sprintf('Attendance recorded for worker #%d on %s', $worker->id, $day),
                $attendance->fresh(),
                [
                    'worker_id' => $worker->id,
                    'date' => $day,
                    'status' => $attendance->status,
                    'late_minutes' => $attendance->late_minutes,
                    'forfeit_day' => $attendance->forfeit_day,
                ],
                $actor,
            );

            return $attendance->fresh(['worker', 'project', 'penalty', 'enteredBy']);
        });
    }

    /**
     * If late > 30m and no forfeit penalty yet, create FORFEIT_DAY = full day salary.
     */
    public function applyForfeitIfNeeded(Attendance $attendance, ?User $actor = null): Attendance
    {
        $attendance = $attendance->fresh(['worker', 'penalty']) ?? $attendance;

        if (! $attendance->shouldForfeitDay()) {
            // Clear forfeit flag if late no longer qualifies (and no penalty linked)
            if ($attendance->forfeit_day && ! $attendance->penalty_id) {
                $attendance->forfeit_day = false;
                $attendance->save();
            }

            return $attendance;
        }

        $attendance->forfeit_day = true;

        if ($attendance->penalty_id) {
            $attendance->save();

            return $attendance;
        }

        $worker = $attendance->worker;
        if (! $worker) {
            throw new InvalidArgumentException('Attendance worker missing for forfeit.');
        }

        $projectId = $attendance->project_id ?: $worker->project_id;
        if (! $projectId) {
            throw new InvalidArgumentException('Project required to record day forfeit penalty.');
        }

        [$amountUsd, $amountIqd, $currency] = $this->dailyForfeitAmounts($worker);

        $penalty = $this->penalties->create([
            'worker_id' => $worker->id,
            'project_id' => $projectId,
            'floor_id' => $attendance->floor_id,
            'type' => Penalty::TYPE_FORFEIT_DAY,
            'reason' => sprintf(
                'Full day cut — late %d min (> %d) on %s',
                (int) $attendance->late_minutes,
                Attendance::LATE_FORFEIT_MINUTES,
                Carbon::parse($attendance->date)->toDateString(),
            ),
            'amount_usd' => $amountUsd,
            'amount_iqd' => $amountIqd,
            'currency' => $currency,
            'occurred_on' => Carbon::parse($attendance->date)->toDateString(),
            'status' => Penalty::STATUS_APPLIED,
            'created_by' => $actor?->id ?? $attendance->entered_by,
            'notes' => 'Auto-created from attendance late forfeit',
        ]);

        $attendance->penalty_id = $penalty->id;
        $attendance->save();

        $this->audit->log(
            AuditActions::ATTENDANCE_FORFEIT_DAY,
            sprintf('Day forfeit penalty #%d for attendance #%d', $penalty->id, $attendance->id),
            $attendance,
            [
                'penalty_id' => $penalty->id,
                'amount_usd' => $amountUsd,
                'amount_iqd' => $amountIqd,
                'late_minutes' => $attendance->late_minutes,
            ],
            $actor,
        );

        return $attendance->fresh(['penalty', 'worker']);
    }

    /**
     * @return array{0: float, 1: float, 2: string} USD, IQD, primary currency
     */
    public function dailyForfeitAmounts(Worker $worker): array
    {
        $days = max(1, (int) config('attendance.days_per_month', 30));
        $monthlyUsd = round((float) ($worker->monthly_salary_usd ?? 0), 2);
        $monthlyIqd = round((float) ($worker->monthly_salary_iqd ?? 0), 2);
        $dailyRateUsd = round((float) ($worker->daily_rate_usd ?? 0), 2);

        if ($monthlyIqd > 0 && $monthlyUsd <= 0) {
            return [0.0, round($monthlyIqd / $days, 2), DualCurrency::IQD];
        }
        if ($monthlyUsd > 0) {
            return [round($monthlyUsd / $days, 2), 0.0, DualCurrency::USD];
        }
        if ($dailyRateUsd > 0) {
            return [$dailyRateUsd, 0.0, DualCurrency::USD];
        }

        // Fallback minimal USD so penalty validation passes (tests can set salary)
        throw new InvalidArgumentException(
            'Worker needs monthly_salary_usd/iqd or daily_rate_usd to compute day forfeit.'
        );
    }

    /**
     * @return Collection<int, Attendance>
     */
    public function markAbsentIfNoCheckIn(
        CarbonInterface|string $date,
        ?string $cutoff = null,
        ?CarbonInterface $now = null,
        ?User $actor = null,
    ): Collection {
        $day = Carbon::parse($date)->startOfDay();
        $cutoff = $cutoff ?? (string) config('attendance.absent_cutoff', '10:00');
        $now = $now ? Carbon::parse($now) : now();
        $cutoffAt = $this->combineDateAndTime($day, $cutoff);

        if ($now->lessThan($cutoffAt)) {
            return collect();
        }

        $marked = collect();

        Worker::query()
            ->where('labor_kind', Worker::LABOR_KIND_WORKER)
            ->orderBy('id')
            ->each(function (Worker $worker) use ($day, $marked, $actor) {
                $attendance = $this->findOrMake($worker->id, $day->toDateString());

                if ($attendance->exists && $attendance->isLeave()) {
                    return;
                }

                if ($attendance->check_in !== null && $attendance->check_in !== '') {
                    return;
                }

                $attendance->status = Attendance::STATUS_ABSENT_UNEXCUSED;
                $attendance->late_minutes = 0;
                $attendance->overtime_hours = 0;
                $attendance->forfeit_day = false;
                $attendance->check_in = null;
                $attendance->check_out = null;
                $attendance->project_id = $attendance->project_id ?: $worker->project_id;
                $attendance->entered_by = $actor?->id ?? $attendance->entered_by;
                $attendance->save();

                $marked->push($attendance);
            });

        return $marked;
    }

    protected function findOrMake(int $workerId, string $date): Attendance
    {
        $attendance = Attendance::query()
            ->where('worker_id', $workerId)
            ->whereDate('date', $date)
            ->first();

        if ($attendance) {
            return $attendance;
        }

        $attendance = new Attendance([
            'worker_id' => $workerId,
            'date' => $date,
            'status' => Attendance::STATUS_PRESENT,
            'shift_start' => (string) config('attendance.shift_start', '08:00'),
        ]);

        return $attendance;
    }

    protected function combineDateAndTime(CarbonInterface $date, string $time): Carbon
    {
        $time = strlen($time) === 5 ? $time.':00' : $time;

        return Carbon::parse($date->toDateString().' '.$time);
    }
}
