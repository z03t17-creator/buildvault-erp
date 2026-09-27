<?php

namespace Tests\Unit;

use App\Models\Attendance;
use App\Models\Worker;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'attendance.shift_start' => '08:00',
            'attendance.shift_end' => '17:00',
            'attendance.late_grace_minutes' => 0,
            'attendance.absent_cutoff' => '10:00',
        ]);

        $this->service = new AttendanceService;
    }

    public function test_compute_late_minutes_on_time_and_late(): void
    {
        $date = Carbon::parse('2026-09-27');

        $this->assertSame(0, $this->service->computeLateMinutes('08:00', $date));
        $this->assertSame(0, $this->service->computeLateMinutes('07:50', $date));
        $this->assertSame(15, $this->service->computeLateMinutes('08:15', $date));
        $this->assertSame(0, $this->service->computeLateMinutes(null, $date));
    }

    public function test_compute_overtime_hours(): void
    {
        $date = Carbon::parse('2026-09-27');

        $this->assertSame(0.0, $this->service->computeOvertimeHours('17:00', $date));
        $this->assertSame(0.0, $this->service->computeOvertimeHours('16:30', $date));
        $this->assertSame(2.0, $this->service->computeOvertimeHours('19:00', $date));
        $this->assertSame(0.5, $this->service->computeOvertimeHours('17:30', $date));
    }

    public function test_record_sets_late_status_and_ot(): void
    {
        $worker = Worker::query()->create(['name' => 'Late Worker']);

        $attendance = $this->service->record($worker, '2026-09-27', [
            'check_in' => '08:30',
            'check_out' => '18:00',
        ]);

        $this->assertSame(30, $attendance->late_minutes);
        $this->assertSame('1.00', (string) $attendance->overtime_hours);
        $this->assertSame(Attendance::STATUS_LATE, $attendance->status);
    }

    public function test_record_present_when_on_time(): void
    {
        $worker = Worker::query()->create(['name' => 'On Time']);

        $attendance = $this->service->record($worker, '2026-09-27', [
            'check_in' => '08:00',
            'check_out' => '17:00',
        ]);

        $this->assertSame(0, $attendance->late_minutes);
        $this->assertSame('0.00', (string) $attendance->overtime_hours);
        $this->assertSame(Attendance::STATUS_PRESENT, $attendance->status);
    }

    public function test_mark_absent_if_no_check_in_after_cutoff(): void
    {
        $worker = Worker::query()->create(['name' => 'No Show']);
        $date = Carbon::parse('2026-09-27');

        $marked = $this->service->markAbsentIfNoCheckIn(
            $date,
            '10:00',
            Carbon::parse('2026-09-27 10:01'),
        );

        $this->assertCount(1, $marked);
        $this->assertSame(Attendance::STATUS_ABSENT_UNEXCUSED, $marked->first()->status);

        $row = Attendance::query()->where('worker_id', $worker->id)->first();
        $this->assertNotNull($row);
        $this->assertSame(Attendance::STATUS_ABSENT_UNEXCUSED, $row->status);
    }

    public function test_mark_absent_skips_before_cutoff_and_checked_in_workers(): void
    {
        $checkedIn = Worker::query()->create(['name' => 'Checked In']);
        Worker::query()->create(['name' => 'Missing']);

        $this->service->record($checkedIn, '2026-09-27', [
            'check_in' => '08:00',
            'check_out' => null,
        ]);

        $before = $this->service->markAbsentIfNoCheckIn(
            '2026-09-27',
            '10:00',
            Carbon::parse('2026-09-27 09:00'),
        );
        $this->assertCount(0, $before);

        $after = $this->service->markAbsentIfNoCheckIn(
            '2026-09-27',
            '10:00',
            Carbon::parse('2026-09-27 11:00'),
        );

        $this->assertCount(1, $after);
        $this->assertSame('Missing', $after->first()->worker->name);
        $this->assertSame(
            Attendance::STATUS_PRESENT,
            Attendance::query()->where('worker_id', $checkedIn->id)->value('status'),
        );
    }

    public function test_leave_status_not_overwritten_by_recalculate(): void
    {
        $worker = Worker::query()->create(['name' => 'Sick']);
        $attendance = Attendance::query()->create([
            'worker_id' => $worker->id,
            'date' => '2026-09-27',
            'status' => Attendance::STATUS_LEAVE_SICK,
            'check_in' => null,
            'check_out' => null,
        ]);

        $this->service->recalculate($attendance)->save();

        $this->assertSame(Attendance::STATUS_LEAVE_SICK, $attendance->fresh()->status);
    }
}
