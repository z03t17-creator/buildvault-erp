<?php

namespace Tests\Unit;

use App\Models\EmployeeAdvance;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Worker;
use App\Services\InsuranceSettings;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayrollServiceTest extends TestCase
{
    use RefreshDatabase;

    private PayrollService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Keep legacy unit expectations: zero insurance unless a test opts in.
        Setting::putValue(InsuranceSettings::HOLDBACK_PCT_KEY, 0);

        Http::fake([
            '*' => Http::response([
                'result' => 'success',
                'rates' => ['IQD' => 1310],
            ], 200),
        ]);

        $this->service = new PayrollService;
    }

    public function test_base_salary_without_attendance(): void
    {
        $worker = $this->worker(daily: 100, ot: 75);

        $result = $this->service->calculate($worker, '2026-09-01', '2026-09-30');

        $this->assertSame(100.0, $result['base_pay_usd']);
        $this->assertSame(0.0, $result['overtime_hours']);
        $this->assertSame(0.0, $result['overtime_pay_usd']);
        $this->assertSame(0.0, $result['penalties_usd']);
        $this->assertSame(100.0, $result['net_pay_usd']);
    }

    public function test_monthly_salary_usd_preferred_over_daily_rate(): void
    {
        $worker = Worker::query()->create([
            'name' => 'Monthly Preferred',
            'daily_rate_usd' => 100,
            'monthly_salary_usd' => 450,
            'overtime_rate_usd' => 0,
        ]);

        $result = $this->service->calculate($worker, '2026-09-01', '2026-09-30');

        $this->assertSame(450.0, $result['base_pay_usd']);
        $this->assertSame(450.0, $result['net_pay_usd']);
    }

    public function test_manual_ot_hours_on_worker(): void
    {
        $worker = $this->worker(daily: 40, ot: 60, manualOt: 2.5);

        $result = $this->service->calculate($worker, '2026-09-01', '2026-09-01');

        $this->assertSame(40.0, $result['base_pay_usd']);
        $this->assertSame(2.5, $result['overtime_hours']);
        $this->assertSame(150.0, $result['overtime_pay_usd']); // 2.5 * 60
        $this->assertSame(190.0, $result['net_pay_usd']);
    }

    public function test_manual_ot_argument_overrides_worker_field(): void
    {
        $worker = $this->worker(daily: 50, ot: 10, manualOt: 99);

        $result = $this->service->calculate($worker, '2026-09-01', '2026-09-30', 3.0);

        $this->assertSame(3.0, $result['overtime_hours']);
        $this->assertSame(30.0, $result['overtime_pay_usd']);
        $this->assertSame(80.0, $result['net_pay_usd']);
    }

    public function test_zero_rates_and_empty_ot(): void
    {
        $worker = $this->worker(daily: 0, ot: 0);

        $result = $this->service->calculate($worker, '2026-09-01', '2026-09-30');

        $this->assertSame(0.0, $result['base_pay_usd']);
        $this->assertSame(0.0, $result['net_pay_usd']);
    }

    public function test_net_deducts_advances_and_insurance_holdback(): void
    {
        Setting::putValue(InsuranceSettings::HOLDBACK_PCT_KEY, 10);

        $project = Project::query()->create(['name' => 'Payroll Deduct Site']);
        $worker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Deduct Worker',
            'daily_rate_usd' => 100,
            'overtime_rate_usd' => 100,
            'manual_ot_hours' => 0,
        ]);

        EmployeeAdvance::query()->create([
            'worker_id' => $worker->id,
            'project_id' => $project->id,
            'amount_iqd' => 13100,
            'remaining_iqd' => 13100,
            'advanced_on' => '2026-09-01',
            'reason' => 'Advance',
            'repayment_method' => EmployeeAdvance::REPAY_PAYROLL,
            'status' => EmployeeAdvance::STATUS_OPEN,
        ]);

        $result = $this->service->calculate($worker, '2026-09-01', '2026-09-01');

        $this->assertSame(100.0, $result['gross_pay_usd']);
        $this->assertSame(10.0, $result['insurance_holdback_usd']);
        $this->assertSame(13100.0, $result['advances_iqd']);
        $this->assertSame(10.0, $result['advances_usd']);
        // 100 - 0 penalties - 10 advances - 10 holdback
        $this->assertSame(80.0, $result['net_pay_usd']);
    }

    public function test_net_deducts_recorded_penalty_with_advance_and_holdback(): void
    {
        Setting::putValue(InsuranceSettings::HOLDBACK_PCT_KEY, 10);

        $project = Project::query()->create(['name' => 'Triple Deduct Unit']);
        $worker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Unit Triple Worker',
            'daily_rate_usd' => 100,
            'overtime_rate_usd' => 100,
        ]);

        EmployeeAdvance::query()->create([
            'worker_id' => $worker->id,
            'project_id' => $project->id,
            'amount_iqd' => 13100,
            'remaining_iqd' => 13100,
            'advanced_on' => '2026-09-01',
            'reason' => 'Advance',
            'repayment_method' => EmployeeAdvance::REPAY_PAYROLL,
            'status' => EmployeeAdvance::STATUS_OPEN,
        ]);

        \App\Models\Penalty::query()->create([
            'worker_id' => $worker->id,
            'project_id' => $project->id,
            'type' => \App\Models\Penalty::TYPE_DAMAGE,
            'reason' => 'Broken tool',
            'amount_usd' => 20,
            'amount_iqd' => 26200,
            'occurred_on' => '2026-09-02',
            'status' => \App\Models\Penalty::STATUS_APPLIED,
        ]);

        $result = $this->service->calculate($worker, '2026-09-01', '2026-09-30');

        $this->assertSame(100.0, $result['gross_pay_usd']);
        $this->assertSame(20.0, $result['recorded_penalties_usd']);
        $this->assertSame(20.0, $result['penalties_usd']);
        $this->assertSame(10.0, $result['advances_usd']);
        $this->assertSame(10.0, $result['insurance_holdback_usd']);
        // 100 - 20 penalties - 10 holdback - 10 advances = 60
        $this->assertSame(60.0, $result['net_pay_usd']);
    }

    private function worker(float $daily, float $ot, float $manualOt = 0): Worker
    {
        return Worker::query()->create([
            'name' => 'Payroll Test Worker',
            'daily_rate_usd' => $daily,
            'overtime_rate_usd' => $ot,
            'manual_ot_hours' => $manualOt,
        ]);
    }
}
