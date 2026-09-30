<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Setting;
use App\Models\Worker;
use App\Services\InsuranceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayrollDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Match historical assertion math (holdback covered in Phase 7 advance tests).
        Setting::putValue(InsuranceSettings::HOLDBACK_PCT_KEY, 0);

        Http::fake([
            'api.exchangerate-api.com/*' => Http::response([
                'result' => 'success',
                'rates' => ['IQD' => 1310],
            ], 200),
            '*' => Http::response([
                'result' => 'success',
                'rates' => ['IQD' => 1310],
            ], 200),
        ]);
    }

    public function test_payroll_dashboard_monthly_summary_per_worker(): void
    {
        $user = $this->userWithRole();
        $project = Project::query()->create(['name' => 'Payroll Site']);

        $worker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Pay Dash Worker',
            'labor_kind' => Worker::LABOR_KIND_WORKER,
            'daily_rate_usd' => 100,
            'overtime_rate_usd' => 75,
            'manual_ot_hours' => 2,
        ]);

        $response = $this->actingAs($user)->get(route('dashboards.payroll', [
            'month' => '2026-09',
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboards/Payroll')
            ->where('month', '2026-09')
            ->where('from', '2026-09-01')
            ->where('to', '2026-09-30')
            ->has('rows', 1)
            ->where('rows.0.worker_id', $worker->id)
            ->where('rows.0.overtime_hours', fn ($v) => (float) $v === 2.0)
            ->where('rows.0.base_pay_usd', fn ($v) => (float) $v === 100.0)
            ->where('rows.0.overtime_pay_usd', fn ($v) => (float) $v === 150.0)
            ->where('rows.0.penalties_usd', fn ($v) => (float) $v === 0.0)
            ->where('rows.0.net_pay_usd', fn ($v) => (float) $v === 250.0)
            ->where('totals.net_pay_usd', fn ($v) => (float) $v === 250.0)
            ->where('rows.0.net_pay_iqd', null)
            ->where('totals.net_pay_iqd', null)
            ->where('autoBlendDisabled', true)
            ->where('exchangeRate', 1310)
            ->missing('rows.0.days_present'));
    }

    public function test_payroll_dashboard_filters_by_project(): void
    {
        $user = $this->userWithRole();
        $a = Project::query()->create(['name' => 'Site A']);
        $b = Project::query()->create(['name' => 'Site B']);

        Worker::query()->create([
            'project_id' => $a->id,
            'name' => 'A Worker',
            'labor_kind' => Worker::LABOR_KIND_WORKER,
        ]);
        Worker::query()->create([
            'project_id' => $b->id,
            'name' => 'B Worker',
            'labor_kind' => Worker::LABOR_KIND_WORKER,
        ]);

        $response = $this->actingAs($user)->get(route('dashboards.payroll', [
            'month' => '2026-09',
            'project_id' => $a->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboards/Payroll')
            ->has('rows', 1)
            ->where('rows.0.name', 'A Worker')
            ->where('projectId', $a->id));
    }
}
