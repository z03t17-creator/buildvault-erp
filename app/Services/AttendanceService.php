<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Penalty;
use App\Models\Project;
use App\Models\Staff;
use App\Models\User;
use App\Support\AuditActions;
use App\Support\DualCurrency;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Attendance desk for monthly + daily staff (unit / piece-rate excluded).
 * Absent / late>30m → forfeit day (+ penalty when a project is available).
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
     * Daily wage for one staff member (monthly/30 or day_rate).
     */
    public function dailyWageFor(Staff $staff): float
    {
        if ($staff->isDaily()) {
            return round((float) ($staff->day_rate ?? 0), 2);
        }

        if ($staff->isMonthly()) {
            $days = max(1, (int) config('attendance.days_per_month', 30));
            $monthly = round((float) ($staff->monthly_salary ?? 0), 2);

            return round($monthly / $days, 2);
        }

        return 0.0;
    }

    /**
     * Recalculate late / OT / status. Does not create penalties (see applyForfeitIfNeeded).
     */
    public function recalculate(Attendance $attendance): Attendance
    {
        if ($attendance->isHalfDay()) {
            $attendance->forfeit_day = false;

            return $attendance;
        }

        if ($attendance->isLeave() && ! $attendance->isHalfDay()) {
            $attendance->forfeit_day = false;

            return $attendance;
        }

        if ($attendance->isAbsent()
            && ($attendance->check_in === null || $attendance->check_in === '')) {
            $attendance->late_minutes = 0;
            $attendance->overtime_hours = 0;
            $attendance->forfeit_day = true;

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
     * Persist attendance for monthly or daily staff.
     *
     * @param  array{
     *   floor_id?: int|null,
     *   project_id?: int|null,
     *   check_in?: string|null,
     *   check_out?: string|null,
     *   shift_start?: string|null,
     *   status?: string|null,
     *   notes?: string|null,
     *   late_minutes?: int|null,
     *   overtime_hours?: float|null,
     *   entered_by?: int|null
     * }  $attributes
     */
    public function record(
        Staff $staff,
        CarbonInterface|string $date,
        array $attributes = [],
        ?User $actor = null,
    ): Attendance {
        if (! $staff->isAttendanceEligible()) {
            throw new InvalidArgumentException('Attendance is only for daily or monthly staff.');
        }

        return DB::transaction(function () use ($staff, $date, $attributes, $actor) {
            $day = Carbon::parse($date)->toDateString();
            $attendance = $this->findOrMake($staff->id, $day);
            $manualLate = array_key_exists('late_minutes', $attributes);
            $manualOt = array_key_exists('overtime_hours', $attributes);

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

            $nonWorkStatuses = [
                Attendance::STATUS_LEAVE_PAID,
                Attendance::STATUS_LEAVE_SICK,
                Attendance::STATUS_ABSENT_UNEXCUSED,
                Attendance::STATUS_HALF_DAY,
            ];

            if (in_array($attendance->status, $nonWorkStatuses, true)
                && (empty($attributes['check_in']) || $attributes['check_in'] === null)) {
                if (! $manualLate) {
                    $attendance->late_minutes = 0;
                }
                if (! $manualOt) {
                    $attendance->overtime_hours = 0;
                }
                $attendance->forfeit_day = $attendance->isAbsent();
                if (in_array($attendance->status, [
                    Attendance::STATUS_LEAVE_PAID,
                    Attendance::STATUS_LEAVE_SICK,
                    Attendance::STATUS_HALF_DAY,
                    Attendance::STATUS_ABSENT_UNEXCUSED,
                ], true)) {
                    $attendance->check_in = null;
                    $attendance->check_out = null;
                }
            } else {
                $this->recalculate($attendance);
            }

            if ($manualLate) {
                $attendance->late_minutes = max(0, (int) $attributes['late_minutes']);
                if ($attendance->isWorked() || $attendance->status === Attendance::STATUS_PRESENT) {
                    $attendance->status = $this->resolveWorkedStatus((int) $attendance->late_minutes);
                    $attendance->forfeit_day = $attendance->shouldForfeitDay();
                }
            }
            if ($manualOt) {
                $attendance->overtime_hours = max(0, round((float) $attributes['overtime_hours'], 2));
            }

            // Present quick-mark: ensure a check-in so the row is recorded.
            if ($attendance->status === Attendance::STATUS_PRESENT
                && ($attendance->check_in === null || $attendance->check_in === '')) {
                $attendance->check_in = $attendance->shift_start
                    ? substr((string) $attendance->shift_start, 0, 5)
                    : (string) config('attendance.shift_start', '08:00');
                $attendance->forfeit_day = false;
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
     * Mark every attendance-eligible staff present for the day.
     *
     * @return Collection<int, Attendance>
     */
    public function markAllPresent(
        CarbonInterface|string $date,
        ?User $actor = null,
        ?int $projectId = null,
    ): Collection {
        $day = Carbon::parse($date)->toDateString();
        $shiftStart = (string) config('attendance.shift_start', '08:00');
        $marked = collect();

        Staff::query()
            ->attendanceEligible()
            ->orderBy('name')
            ->each(function (Staff $staff) use ($day, $actor, $projectId, $shiftStart, $marked) {
                $marked->push($this->record($staff, $day, [
                    'status' => Attendance::STATUS_PRESENT,
                    'check_in' => $shiftStart,
                    'project_id' => $projectId,
                    'entered_by' => $actor?->id,
                ], $actor));
            });

        return $marked;
    }

    /**
     * If absent or late > 30m and no forfeit penalty yet, create FORFEIT_DAY.
     */
    public function applyForfeitIfNeeded(Attendance $attendance, ?User $actor = null): Attendance
    {
        $attendance = $attendance->fresh(['staff', 'penalty']) ?? $attendance;

        if (! $attendance->shouldForfeitDay()) {
            if ($attendance->penalty_id) {
                $penalty = $attendance->penalty;
                $notes = (string) ($penalty?->notes ?? '');
                if ($penalty && str_contains($notes, 'Auto-created from attendance')) {
                    $penalty->delete();
                    $attendance->penalty_id = null;
                }
            }
            if ($attendance->forfeit_day || $attendance->isDirty('penalty_id')) {
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
        if (! $staff || ! $staff->isMonthly()) {
            // Daily staff: forfeit flag is enough (present days drive payout).
            $attendance->save();

            return $attendance;
        }

        $projectId = $attendance->project_id ?: Project::query()->orderBy('id')->value('id');
        if (! $projectId) {
            $attendance->save();

            return $attendance;
        }

        try {
            [$amountUsd, $amountIqd, $currency] = $this->dailyForfeitAmounts($staff);
        } catch (InvalidArgumentException) {
            $attendance->save();

            return $attendance;
        }

        $reason = $attendance->isAbsent()
            ? sprintf('Full day cut — absent on %s', Carbon::parse($attendance->date)->toDateString())
            : sprintf(
                'Full day cut — late %d min (> %d) on %s',
                (int) $attendance->late_minutes,
                Attendance::LATE_FORFEIT_MINUTES,
                Carbon::parse($attendance->date)->toDateString(),
            );

        $penalty = $this->penalties->create([
            'staff_id' => $staff->id,
            'project_id' => $projectId,
            'floor_id' => $attendance->floor_id,
            'type' => $attendance->isAbsent() ? Penalty::TYPE_ABSENCE : Penalty::TYPE_FORFEIT_DAY,
            'reason' => $reason,
            'amount_usd' => $amountUsd,
            'amount_iqd' => $amountIqd,
            'currency' => $currency,
            'occurred_on' => Carbon::parse($attendance->date)->toDateString(),
            'status' => Penalty::STATUS_APPLIED,
            'created_by' => $actor?->id ?? $attendance->entered_by,
            'notes' => $attendance->isAbsent()
                ? 'Auto-created from attendance absence'
                : 'Auto-created from attendance late forfeit',
        ]);

        // Treat absence penalties like a full day cut in salaryDueFor (amount set to daily).
        if ($attendance->isAbsent() && $penalty->type === Penalty::TYPE_ABSENCE) {
            // salaryDueFor already subtracts amount_usd/iqd for non-forfeit types — amounts are daily.
        }

        $attendance->project_id = $attendance->project_id ?: $projectId;
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
                'status' => $attendance->status,
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
        $daily = $this->dailyWageFor($staff);
        if ($daily <= 0) {
            throw new InvalidArgumentException(
                'Staff needs monthly_salary or day_rate to compute day forfeit.'
            );
        }
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
            ->attendanceEligible()
            ->orderBy('id')
            ->each(function (Staff $staff) use ($day, $marked, $actor) {
                $attendance = $this->findOrMake($staff->id, $day->toDateString());

                if ($attendance->exists && ($attendance->isLeave() || $attendance->isHalfDay())) {
                    return;
                }

                if ($attendance->check_in !== null && $attendance->check_in !== '') {
                    return;
                }

                $marked->push($this->record($staff, $day->toDateString(), [
                    'status' => Attendance::STATUS_ABSENT_UNEXCUSED,
                    'check_in' => null,
                    'check_out' => null,
                    'entered_by' => $actor?->id,
                ], $actor));
            });

        return $marked;
    }

    /**
     * Estimate today's wage cost by currency from a staff×attendance grid.
     *
     * @param  Collection<int, Staff>  $staff
     * @param  Collection<int|string, Attendance>  $attendances keyed by staff_id
     * @return array{USD: float, IQD: float}
     */
    public function estimateDayWages(Collection $staff, Collection $attendances): array
    {
        $totals = [DualCurrency::USD => 0.0, DualCurrency::IQD => 0.0];

        foreach ($staff as $person) {
            /** @var Attendance|null $row */
            $row = $attendances->get($person->id);
            if (! $row) {
                continue;
            }
            $factor = $row->wageDayFactor();
            if ($factor <= 0) {
                continue;
            }
            $wage = round($this->dailyWageFor($person) * $factor, 2);
            $currency = strtoupper((string) ($person->currency ?: DualCurrency::USD));
            if ($currency === DualCurrency::IQD) {
                $totals[DualCurrency::IQD] = round($totals[DualCurrency::IQD] + $wage, 2);
            } else {
                $totals[DualCurrency::USD] = round($totals[DualCurrency::USD] + $wage, 2);
            }
        }

        return $totals;
    }

    /**
     * Count payable attendance days in a month for daily staff (present/late = 1, half = 0.5).
     */
    public function payableDaysInMonth(Staff $staff, CarbonInterface|string $month): float
    {
        $month = Carbon::parse($month)->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $rows = Attendance::query()
            ->where('staff_id', $staff->id)
            ->whereDate('date', '>=', $month->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->get();

        $days = 0.0;
        foreach ($rows as $row) {
            $days = round($days + $row->wageDayFactor(), 2);
        }

        return $days;
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

        return new Attendance([
            'staff_id' => $staffId,
            'date' => $date,
            'status' => Attendance::STATUS_PRESENT,
            'shift_start' => (string) config('attendance.shift_start', '08:00'),
        ]);
    }

    protected function combineDateAndTime(CarbonInterface $date, string $time): Carbon
    {
        $time = strlen($time) === 5 ? $time.':00' : $time;

        return Carbon::parse($date->toDateString().' '.$time);
    }
}
