<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\EmployeeAdvance;
use App\Models\Penalty;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Worker;
use App\Services\InsuranceSettings;
use App\Support\Roles;
use Database\Seeders\DemoAdvancesSeeder;
use Database\Seeders\DemoHierarchySeeder;
use Database\Seeders\DemoInsuranceSeeder;
use Database\Seeders\DemoPenaltiesSeeder;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PayrollInsurancePhase8Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*' => Http::response([
                'result' => 'success',
                'rates' => ['IQD' => 1310],
            ], 200),
        ]);
    }

    public function test_net_formula_with_penalty_advance_and_holdback(): void
    {
        Setting::putValue(InsuranceSettings::HOLDBACK_PCT_KEY, 10);

        $admin = $this->userWithRole(Roles::SUPER_ADMIN);
        $project = Project::query()->create(['name' => 'Phase8 Net Site']);
        $worker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Triple Deduct Worker',
            'daily_rate_usd' => 100,
            'overtime_rate_usd' => 100,
        ]);

        Attendance::query()->create([
            'worker_id' => $worker->id,
            'date' => '2026-09-01',
            'status' => Attendance::STATUS_PRESENT,
            'late_minutes' => 0,
            'overtime_hours' => 0,
            'check_in' => '08:00',
            'check_out' => '17:00',
        ]);

        // Advance 13,100 IQD = 10 USD @ 1310
        EmployeeAdvance::query()->create([
            'worker_id' => $worker->id,
            'project_id' => $project->id,
            'amount_iqd' => 13100,
            'remaining_iqd' => 13100,
            'advanced_on' => '2026-09-01',
            'reason' => 'Phase8 advance',
            'repayment_method' => EmployeeAdvance::REPAY_PAYROLL,
            'status' => EmployeeAdvance::STATUS_OPEN,
            'entered_by' => $admin->id,
        ]);

        // Applied penalty 65,500 IQD = 50 USD
        Penalty::query()->create([
            'worker_id' => $worker->id,
            'project_id' => $project->id,
            'type' => Penalty::TYPE_SAFETY,
            'reason' => 'Phase8 penalty',
            'amount_usd' => 50,
            'amount_iqd' => 65500,
            'occurred_on' => '2026-09-05',
            'status' => Penalty::STATUS_APPLIED,
            'created_by' => $admin->id,
        ]);

        // gross 100 − penalties 50 − holdback 10 − advances 10 = 30
        $this->actingAs($admin)
            ->get(route('dashboards.payroll', ['month' => '2026-09']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboards/Payroll')
                ->where('rows.0.penalties_usd', fn ($v) => (float) $v === 50.0)
                ->where('rows.0.advances_iqd', fn ($v) => (float) $v === 13100.0)
                ->where('rows.0.insurance_holdback_usd', fn ($v) => (float) $v === 10.0)
                ->where('rows.0.net_pay_usd', fn ($v) => (float) $v === 30.0)
                ->where('rows.0.net_pay_iqd', fn ($v) => (float) $v === 39300.0)
            );
    }

    public function test_boss_views_penalties_and_insurance_accountant_operates_stock_blocked(): void
    {
        $this->seed(RoleSeeder::class);
        $boss = $this->userWithRole(Roles::BOSS_CONTRACTOR);
        $accountant = $this->userWithRole(Roles::ACCOUNTANT);
        $stock = $this->userWithRole(Roles::STOCK_MANAGER);

        $project = Project::query()->create(['name' => 'RBAC Site']);
        $worker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'RBAC Worker',
        ]);

        $this->actingAs($boss)->get(route('penalties.index'))->assertOk();
        $this->actingAs($boss)->get(route('penalties.create'))->assertForbidden();
        $this->actingAs($boss)->get(route('retention-holds.index'))->assertOk();
        $this->actingAs($boss)
            ->put(route('retention-holds.settings'), [
                'holdback_pct' => 12,
                'maturity_months' => 6,
            ])
            ->assertForbidden();

        $this->actingAs($accountant)
            ->post(route('penalties.store'), [
                'worker_id' => $worker->id,
                'project_id' => $project->id,
                'type' => Penalty::TYPE_DAMAGE,
                'occurred_on' => '2026-09-10',
                'amount_iqd' => 20000,
                'reason' => 'Broken scaffold clamp',
                'notes' => 'Phase 8 RBAC',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('penalties', [
            'worker_id' => $worker->id,
            'type' => Penalty::TYPE_DAMAGE,
            'status' => Penalty::STATUS_PENDING,
        ]);

        $this->actingAs($accountant)->get(route('retention-holds.index'))->assertOk();

        $this->actingAs($stock)->get(route('penalties.index'))->assertForbidden();
        $this->actingAs($stock)->get(route('retention-holds.index'))->assertForbidden();
        $this->actingAs($stock)->get(route('dashboards.payroll'))->assertForbidden();
    }

    public function test_payout_create_exposes_payroll_net_suggestions(): void
    {
        Setting::putValue(InsuranceSettings::HOLDBACK_PCT_KEY, 10);
        $accountant = $this->userWithRole(Roles::ACCOUNTANT);
        $project = Project::query()->create(['name' => 'Suggest Site']);
        $worker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Suggest Worker',
            'daily_rate_usd' => 50,
            'overtime_rate_usd' => 50,
        ]);

        Attendance::query()->create([
            'worker_id' => $worker->id,
            'date' => now()->toDateString(),
            'status' => Attendance::STATUS_PRESENT,
            'late_minutes' => 0,
            'overtime_hours' => 0,
            'check_in' => '08:00',
            'check_out' => '17:00',
        ]);

        $this->actingAs($accountant)
            ->get(route('payouts.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Payouts/Create')
                ->has('payrollSuggestions')
                ->where("payrollSuggestions.{$worker->id}.insurance_holdback_pct", fn ($v) => (float) $v === 10.0)
            );
    }

    public function test_demo_seed_puts_all_three_deductions_on_engineer(): void
    {
        $this->seed([
            RoleSeeder::class,
            VaultSeeder::class,
            DemoUsersSeeder::class,
            DemoHierarchySeeder::class,
            DemoInsuranceSeeder::class,
            DemoAdvancesSeeder::class,
            DemoPenaltiesSeeder::class,
        ]);

        $engineer = Worker::query()->where('name', 'Demo Engineer')->first();
        $this->assertNotNull($engineer);

        $this->assertTrue(
            EmployeeAdvance::query()
                ->where('worker_id', $engineer->id)
                ->where('status', EmployeeAdvance::STATUS_OPEN)
                ->exists()
        );

        $this->assertTrue(
            Penalty::query()
                ->where('worker_id', $engineer->id)
                ->where('reason', 'like', DemoPenaltiesSeeder::MARKER.'%')
                ->exists()
        );

        $this->assertTrue(
            \App\Models\RetentionHold::query()
                ->where('worker_id', $engineer->id)
                ->exists()
        );
    }
}
