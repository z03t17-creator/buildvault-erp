<?php

namespace Tests\Unit;

use App\Models\Attendance;
use App\Models\Worker;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollServiceTest extends TestCase
{
    use RefreshDatabase;

    private PayrollService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payroll.late_penalty_per_minute_usd' => 0.25,
            'payroll.unexcused_absence_penalty_usd' => null,
            'payroll.unexcused_absence_penalty_multiplier' => 1.0,
            'payroll.count_leave_paid_as_day' => true,
            'payroll.count_leave_sick_as_day' => false,
        ]);

        $this->service = new PayrollService;
    }

    public function test_simple_present_days_no_late_no_ot(): void
    {
        $worker = $this->worker(daily: 50, ot: 75);
        $this->attendance($worker, '2026-09-01', Attendance::STATUS_PRESENT);
        $this->attendance($worker, '2026-09-02', Attendance::STATUS_PRESENT);

        $result = $this->service->calculate($worker, '2026-09-01', '2026-09-30');

        $this->assertSame(2, $result['days_present']);
        $this->assertSame(0.0, $result['overtime_hours']);
        $this->assertSame(0, $result['late_minutes']);
        $this->assertSame(100.0, $result['base_pay_usd']);
        $this->assertSame(0.0, $result['overtime_pay_usd']);
        $this->assertSame(0.0, $result['late_penalty_usd']);
        $this->assertSame(0.0, $result['absence_penalty_usd']);
        $this->assertSame(100.0, $result['net_pay_usd']);
    }

    public function test_overtime_adds_ot_pay(): void
    {
        $worker = $this->worker(daily: 40, ot: 60);
        $this->attendance($worker, '2026-09-01', Attendance::STATUS_PRESENT, late: 0, ot: 2.5);

        $result = $this->service->calculate($worker, '2026-09-01', '2026-09-01');

        $this->assertSame(40.0, $result['base_pay_usd']);
        $this->assertSame(150.0, $result['overtime_pay_usd']); // 2.5 * 60
        $this->assertSame(190.0, $result['net_pay_usd']);
    }

    public function test_late_status_counts_as_day_and_applies_late_penalty(): void
    {
        $worker = $this->worker(daily: 100, ot: 150);
        $this->attendance($worker, '2026-09-01', Attendance::STATUS_LATE, late: 20, ot: 0);

        $result = $this->service->calculate($worker, '2026-09-01', '2026-09-01');

        $this->assertSame(1, $result['days_present']);
        $this->assertSame(20, $result['late_minutes']);
        $this->assertSame(100.0, $result['base_pay_usd']);
        $this->assertSame(5.0, $result['late_penalty_usd']); // 20 * 0.25
        $this->assertSame(95.0, $result['net_pay_usd']);
    }

    public function test_full_unexcused_absence_month_day(): void
    {
        $worker = $this->worker(daily: 80, ot: 100);
        $this->attendance($worker, '2026-09-01', Attendance::STATUS_ABSENT_UNEXCUSED);

        $result = $this->service->calculate($worker, '2026-09-01', '2026-09-01');

        $this->assertSame(0, $result['days_present']);
        $this->assertSame(1, $result['unexcused_absences']);
        $this->assertSame(0.0, $result['base_pay_usd']);
        $this->assertSame(80.0, $result['absence_penalty_usd']); // 1 × daily rate
        $this->assertSame(-80.0, $result['net_pay_usd']);
    }

    public function test_mixed_month_edge_cases(): void
    {
        $worker = $this->worker(daily: 50, ot: 70);

        // 3 present (one with OT), 1 late, 1 unexcused, 1 leave_paid, 1 leave_sick
        $this->attendance($worker, '2026-09-01', Attendance::STATUS_PRESENT, late: 0, ot: 1);
        $this->attendance($worker, '2026-09-02', Attendance::STATUS_PRESENT);
        $this->attendance($worker, '2026-09-03', Attendance::STATUS_PRESENT);
        $this->attendance($worker, '2026-09-04', Attendance::STATUS_LATE, late: 40, ot: 0);
        $this->attendance($worker, '2026-09-05', Attendance::STATUS_ABSENT_UNEXCUSED);
        $this->attendance($worker, '2026-09-06', Attendance::STATUS_LEAVE_PAID);
        $this->attendance($worker, '2026-09-07', Attendance::STATUS_LEAVE_SICK);

        $result = $this->service->calculate($worker, '2026-09-01', '2026-09-30');

        // days: 3 present + 1 late + 1 leave_paid = 5 (sick not counted)
        $this->assertSame(5, $result['days_present']);
        $this->assertSame(1.0, $result['overtime_hours']);
        $this->assertSame(40, $result['late_minutes']);
        $this->assertSame(1, $result['unexcused_absences']);

        $this->assertSame(250.0, $result['base_pay_usd']); // 5 * 50
        $this->assertSame(70.0, $result['overtime_pay_usd']); // 1 * 70
        $this->assertSame(10.0, $result['late_penalty_usd']); // 40 * 0.25
        $this->assertSame(50.0, $result['absence_penalty_usd']); // 1 * 50
        // net = 250 + 70 - 10 - 50 = 260
        $this->assertSame(260.0, $result['net_pay_usd']);
    }

    public function test_explicit_absence_penalty_override(): void
    {
        config(['payroll.unexcused_absence_penalty_usd' => 25]);

        $worker = $this->worker(daily: 100, ot: 100);
        $this->attendance($worker, '2026-09-01', Attendance::STATUS_ABSENT_UNEXCUSED);
        $this->attendance($worker, '2026-09-02', Attendance::STATUS_ABSENT_UNEXCUSED);

        $result = $this->service->calculate($worker, '2026-09-01', '2026-09-02');

        $this->assertSame(50.0, $result['absence_penalty_usd']);
        $this->assertSame(-50.0, $result['net_pay_usd']);
    }

    public function test_zero_rates_and_empty_period(): void
    {
        $worker = $this->worker(daily: 0, ot: 0);

        $result = $this->service->calculate($worker, '2026-09-01', '2026-09-30');

        $this->assertSame(0, $result['days_present']);
        $this->assertSame(0.0, $result['net_pay_usd']);
    }

    public function test_date_range_excludes_outside_rows(): void
    {
        $worker = $this->worker(daily: 10, ot: 20);
        $this->attendance($worker, '2026-08-31', Attendance::STATUS_PRESENT);
        $this->attendance($worker, '2026-09-01', Attendance::STATUS_PRESENT);
        $this->attendance($worker, '2026-10-01', Attendance::STATUS_PRESENT);

        $result = $this->service->calculate($worker, '2026-09-01', '2026-09-30');

        $this->assertSame(1, $result['days_present']);
        $this->assertSame(10.0, $result['net_pay_usd']);
    }

    private function worker(float $daily, float $ot): Worker
    {
        return Worker::query()->create([
            'name' => 'Payroll Test Worker',
            'daily_rate_usd' => $daily,
            'overtime_rate_usd' => $ot,
        ]);
    }

    private function attendance(
        Worker $worker,
        string $date,
        string $status,
        int $late = 0,
        float $ot = 0,
    ): Attendance {
        return Attendance::query()->create([
            'worker_id' => $worker->id,
            'date' => $date,
            'status' => $status,
            'late_minutes' => $late,
            'overtime_hours' => $ot,
            'check_in' => $status === Attendance::STATUS_ABSENT_UNEXCUSED ? null : '08:00',
            'check_out' => $status === Attendance::STATUS_ABSENT_UNEXCUSED ? null : '17:00',
        ]);
    }
}
