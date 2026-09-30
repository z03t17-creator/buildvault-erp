<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Setting;
use App\Models\Worker;
use App\Services\InsuranceSettings;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PayrollPhase19Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::putValue(InsuranceSettings::HOLDBACK_PCT_KEY, 0);

        Http::fake([
            '*' => Http::response([
                'result' => 'success',
                'rates' => ['IQD' => 1310],
            ], 200),
        ]);
    }

    public function test_payroll_lists_salary_workers_with_monthly_base_and_dual_money(): void
    {
        $project = Project::query()->create([
            'name' => 'Payroll UX Site',
            'status' => Project::STATUS_ACTIVE,
        ]);

        $worker = Worker::query()->create([
            'name' => 'Salary Row',
            'project_id' => $project->id,
            'labor_kind' => Worker::LABOR_KIND_WORKER,
            'role' => Worker::ROLE_LABORER,
            'monthly_salary_usd' => 450,
            'monthly_salary_iqd' => 585000,
        ]);

        Worker::query()->create([
            'name' => 'Unit Staff Skip',
            'project_id' => $project->id,
            'labor_kind' => Worker::LABOR_KIND_STAFF,
            'role' => Worker::ROLE_SUBCONTRACTOR,
            'unit_rate' => 12,
            'rate_unit' => 'm2',
            'rate_currency' => 'USD',
            'daily_rate_usd' => 999,
        ]);

        $this->actingAsRole(Roles::ACCOUNTANT);

        $this->get(route('dashboards.payroll', ['month' => '2026-09']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboards/Payroll')
                ->where('month', '2026-09')
                ->has('rows', 1)
                ->where('rows.0.worker_id', $worker->id)
                ->where('rows.0.base_pay_usd', fn ($v) => (float) $v === 450.0)
                ->where('rows.0.monthly_salary_iqd', fn ($v) => (float) $v === 585000.0)
                ->where('rows.0.net_pay_usd', fn ($v) => (float) $v === 450.0)
                ->where('totals.workers', 1)
                ->where('totals.net_pay_usd', fn ($v) => (float) $v === 450.0)
                ->where('totals.monthly_salary_iqd', fn ($v) => (float) $v === 585000.0)
                ->missing('netFormula')
            );
    }

    public function test_payroll_excludes_staff_and_unclassified(): void
    {
        $project = Project::query()->create([
            'name' => 'Filter Site',
            'status' => Project::STATUS_ACTIVE,
        ]);

        Worker::query()->create([
            'name' => 'Only Staff',
            'project_id' => $project->id,
            'labor_kind' => Worker::LABOR_KIND_STAFF,
            'daily_rate_usd' => 200,
        ]);

        Worker::query()->create([
            'name' => 'Unclassified',
            'project_id' => $project->id,
            'labor_kind' => Worker::LABOR_KIND_UNCLASSIFIED,
            'daily_rate_usd' => 150,
        ]);

        $this->actingAsRole(Roles::SUPER_ADMIN);

        $this->get(route('dashboards.payroll', ['month' => '2026-09']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboards/Payroll')
                ->has('rows', 0)
                ->where('totals.workers', 0)
            );
    }
}
