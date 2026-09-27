<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Worker;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class AttendanceService
{
    /**
     * Minutes late after shift start (respecting grace). Zero if on time or missing check-in.
     */
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

    /**
     * Overtime hours after shift end. Zero if missing check-out or left on/before end.
     */
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

    /**
     * Derive present/late status from late minutes (leaves/absent unchanged).
     */
    public function resolveWorkedStatus(int $lateMinutes): string
    {
        return $lateMinutes > 0
            ? Attendance::STATUS_LATE
            : Attendance::STATUS_PRESENT;
    }

    /**
     * Recalculate late, OT, and worked status on an attendance row (skips leave statuses).
     */
    public function recalculate(Attendance $attendance): Attendance
    {
        if ($attendance->isLeave()) {
            return $attendance;
        }

        if ($attendance->status === Attendance::STATUS_ABSENT_UNEXCUSED
            && ($attendance->check_in === null || $attendance->check_in === '')) {
            $attendance->late_minutes = 0;
            $attendance->overtime_hours = 0;

            return $attendance;
        }

        $late = $this->computeLateMinutes(
            $attendance->check_in,
            $attendance->date,
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

        return $attendance;
    }

    /**
     * Persist check-in/out and recalculate metrics.
     *
     * @param  array{floor_id?: int|null, check_in?: string|null, check_out?: string|null, status?: string|null}  $attributes
     */
    public function record(
        Worker $worker,
        CarbonInterface|string $date,
        array $attributes = [],
    ): Attendance {
        $day = Carbon::parse($date)->toDateString();

        $attendance = $this->findOrMake($worker->id, $day);

        if (array_key_exists('floor_id', $attributes)) {
            $attendance->floor_id = $attributes['floor_id'];
        }
        if (array_key_exists('check_in', $attributes)) {
            $attendance->check_in = $attributes['check_in'];
        }
        if (array_key_exists('check_out', $attributes)) {
            $attendance->check_out = $attributes['check_out'];
        }
        if (! empty($attributes['status']) && in_array($attributes['status'], Attendance::STATUSES, true)) {
            $attendance->status = $attributes['status'];
        }

        $this->recalculate($attendance);
        $attendance->save();

        return $attendance->refresh();
    }

    /**
     * Mark workers with no check-in by cutoff as absent_unexcused for the given date.
     * Skips leave records and workers who already checked in.
     *
     * @return Collection<int, Attendance>
     */
    public function markAbsentIfNoCheckIn(
        CarbonInterface|string $date,
        ?string $cutoff = null,
        ?CarbonInterface $now = null,
    ): Collection {
        $day = Carbon::parse($date)->startOfDay();
        $cutoff = $cutoff ?? (string) config('attendance.absent_cutoff', '10:00');
        $now = $now ? Carbon::parse($now) : now();
        $cutoffAt = $this->combineDateAndTime($day, $cutoff);

        if ($now->lessThan($cutoffAt)) {
            return collect();
        }

        $marked = collect();

        Worker::query()->orderBy('id')->each(function (Worker $worker) use ($day, $marked) {
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
            $attendance->check_in = null;
            $attendance->check_out = null;
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

        return new Attendance([
            'worker_id' => $workerId,
            'date' => $date,
        ]);
    }

    protected function combineDateAndTime(CarbonInterface $date, string $time): Carbon
    {
        $normalized = strlen($time) === 5 ? "{$time}:00" : $time;

        return Carbon::parse($date->format('Y-m-d').' '.$normalized);
    }
}

