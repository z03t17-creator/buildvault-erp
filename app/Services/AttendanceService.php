<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Penalty;
use App\Models\User;
use App\Models\Staff;
use App\Support\AuditActions;
use App\Support\DualCurrency;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Salary-staff attendance. Stock Manager is the primary writer.
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
     * Persist attendance for salary-kind staff. Applies late forfeit when due.
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
        Staff $staff,
        CarbonInterface|string $date,
        array $attributes = [],
        ?User $actor = null,
    ): Attendance {
        if (! $staff->isSalary()) {
            throw new InvalidArgumentException('Attendance is only for salary-kind staff.');
        }

        return DB::transaction(function () use ($staff, $date, $attributes, $actor) {
            $day = Carbon::parse($date)->toDateString();
            $attendance = $this->findOrMake($staff->id, $day);

            if (array_key_exists('floor_id', $attributes)) {
                $attendance->floor_id = $attributes['floor_id'];
            }
            if (array_key_exists('project_id', $attributes)) {
                $attendance->project_id = $attributes['project_id'] ?? null;
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
            $this->applyForfeitIfNeeded($attendance->fresh(['staff', 'penalty']), $actor);

            $this->audit->log(
                AuditActions::ATTENDANCE_RECORDED,
                sprintf('Attendance recorded for staff #%d on %s', $staff->id, $day),
                $attendance->fresh(),
                [
                    'staff_id' => $staff->id,
                    'date' => $day,
                    'status' => $attendance->status,
                    'late_minutes' => $attendance->late_minutes,
                    'forfeit_day' => $attendance->forfeit_day,
                ],
                $actor,
            );

            return $attendance->fresh(['staff', 'project', 'penalty', 'enteredBy']);
        });
    }

    /**
     * If late > 30m and no forfeit penalty yet, create FORFEIT_DAY = full day salary.
     */
    public function applyForfeitIfNeeded(Attendance $attendance, ?User $actor = null): Attendance
    {
        $attendance = $attendance->fresh(['staff', 'penalty']) ?? $attendance;

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

        $staff = $attendance->staff;
        if (! $staff) {
            throw new InvalidArgumentException('Attendance staff missing for forfeit.');
        }

        $projectId = $attendance->project_id;
        if (! $projectId) {
            throw new InvalidArgumentException('Project required to record day forfeit penalty.');
        }

        [$amountUsd, $amountIqd, $currency] = $this->dailyForfeitAmounts($staff);

        $penalty = $this->penalties->create([
            'staff_id' => $staff->id,
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

        return $attendance->fresh(['penalty', 'staff']);
    }

    /**
     * @return array{0: float, 1: float, 2: string} USD, IQD, primary currency
     */
    public function dailyForfeitAmounts(Staff $staff): array
    {
        $days = max(1, (int) config('attendance.days_per_month', 30));
        $monthly = round((float) ($staff->monthly_salary ?? 0), 2);
        if ($monthly <= 0) {
            throw new InvalidArgumentException(
                'Staff needs monthly_salary to compute day forfeit.'
            );
        }
        $daily = round($monthly / $days, 2);
        $currency = strtoupper((string) $staff->currency);
        if ($currency === DualCurrency::IQD) {
            return [0.0, $daily, DualCurrency::IQD];
        }

        return [$daily, 0.0, DualCurrency::USD];
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

        Staff::query()
            ->where('kind', Staff::KIND_SALARY)
            ->orderBy('id')
            ->each(function (Staff $staff) use ($day, $marked, $actor) {
                $attendance = $this->findOrMake($staff->id, $day->toDateString());

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
                $attendance->entered_by = $actor?->id ?? $attendance->entered_by;
                $attendance->save();

                $marked->push($attendance);
            });

        return $marked;
    }

    protected function findOrMake(int $staffId, string $date): Attendance
    {
        $attendance = Attendance::query()
            ->where('staff_id', $staffId)
            ->whereDate('date', $date)
            ->first();

        if ($attendance) {
            return $attendance;
        }

        $attendance = new Attendance([
            'staff_id' => $staffId,
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
