<?php

namespace Tests\Feature;

use App\Models\EmployeeAdvance;
use App\Models\Project;
use App\Models\Vault;
use App\Models\Worker;
use App\Support\Roles;
use Database\Seeders\DemoAdvancesSeeder;
use Database\Seeders\DemoHierarchySeeder;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeAdvancePhase7Test extends TestCase
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

        Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 50_000,
            'balance_iqd' => 65_500_000,
        ]);
    }

    public function test_accountant_can_record_and_repay_advance(): void
    {
        $accountant = $this->userWithRole(Roles::ACCOUNTANT);
        $project = Project::query()->create(['name' => 'Advance Site']);
        $worker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Advance Worker',
            'daily_rate_usd' => 40,
        ]);

        $this->actingAs($accountant)
            ->post(route('advances.store'), [
                'worker_id' => $worker->id,
                'project_id' => $project->id,
                'amount_iqd' => 250000,
                'remaining_iqd' => 250000,
                'advanced_on' => '2026-09-15',
                'reason' => 'Tools cash',
                'repayment_method' => EmployeeAdvance::REPAY_PAYROLL,
                'notes' => 'Phase 7 test',
            ])
            ->assertRedirect();

        $advance = EmployeeAdvance::query()->first();
        $this->assertNotNull($advance);
        $this->assertSame('250000.00', (string) $advance->amount_iqd);
        $this->assertSame(EmployeeAdvance::STATUS_OPEN, $advance->status);
        $this->assertSame($accountant->id, $advance->entered_by);

        $this->actingAs($accountant)
            ->post(route('advances.repay', $advance), ['amount_iqd' => 100000])
            ->assertRedirect();

        $advance->refresh();
        $this->assertSame('150000.00', (string) $advance->remaining_iqd);
        $this->assertSame(EmployeeAdvance::STATUS_OPEN, $advance->status);

        $this->actingAs($accountant)
            ->post(route('advances.repay', $advance), ['amount_iqd' => 150000])
            ->assertRedirect();

        $advance->refresh();
        $this->assertSame('0.00', (string) $advance->remaining_iqd);
        $this->assertSame(EmployeeAdvance::STATUS_REPAID, $advance->status);
    }

    public function test_boss_can_view_but_not_create_advances(): void
    {
        $boss = $this->userWithRole(Roles::BOSS_CONTRACTOR);
        $project = Project::query()->create(['name' => 'Boss View Site']);
        $worker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Boss View Worker',
        ]);

        $this->actingAs($boss)->get(route('advances.index'))->assertOk();
        $this->actingAs($boss)->get(route('advances.create'))->assertForbidden();
        $this->actingAs($boss)
            ->post(route('advances.store'), [
                'worker_id' => $worker->id,
                'project_id' => $project->id,
                'amount_iqd' => 10000,
                'advanced_on' => '2026-09-15',
                'reason' => 'Nope',
                'repayment_method' => EmployeeAdvance::REPAY_CASH,
            ])
            ->assertForbidden();
    }

    public function test_stock_manager_cannot_access_advances(): void
    {
        $stock = $this->userWithRole(Roles::STOCK_MANAGER);

        $this->actingAs($stock)->get(route('advances.index'))->assertForbidden();
        $this->actingAs($stock)->get(route('advances.create'))->assertForbidden();
    }

    public function test_payroll_dashboard_shows_advances_and_insurance_holdback(): void
    {
        $admin = $this->userWithRole(Roles::SUPER_ADMIN);
        $project = Project::query()->create(['name' => 'Payroll Adv Site']);
        $worker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Net Deduct Worker',
            'daily_rate_usd' => 100,
            'overtime_rate_usd' => 100,
        ]);

        // 1 present day → base 100; 10% insurance = 10; advance 13100 IQD = 10 USD @ 1310
        EmployeeAdvance::query()->create([
            'worker_id' => $worker->id,
            'project_id' => $project->id,
            'amount_iqd' => 13100,
            'remaining_iqd' => 13100,
            'advanced_on' => '2026-09-01',
            'reason' => 'Payroll deduct test',
            'repayment_method' => EmployeeAdvance::REPAY_PAYROLL,
            'status' => EmployeeAdvance::STATUS_OPEN,
            'entered_by' => $admin->id,
        ]);

        \App\Models\Attendance::query()->create([
            'worker_id' => $worker->id,
            'date' => '2026-09-01',
            'status' => \App\Models\Attendance::STATUS_PRESENT,
            'late_minutes' => 0,
            'overtime_hours' => 0,
            'check_in' => '08:00',
            'check_out' => '17:00',
        ]);

        $this->actingAs($admin)
            ->get(route('dashboards.payroll', ['month' => '2026-09']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboards/Payroll')
                ->where('rows.0.advances_iqd', fn ($v) => (float) $v === 13100.0)
                ->where('rows.0.insurance_holdback_usd', fn ($v) => (float) $v === 10.0)
                // net = 100 - 0 penalties - 10 advances - 10 holdback = 80
                ->where('rows.0.net_pay_usd', fn ($v) => (float) $v === 80.0)
            );
    }

    public function test_demo_advances_seeder_is_idempotent(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(VaultSeeder::class);
        Vault::query()->where('name', VaultSeeder::NAME)->update([
            'balance_usd' => 50_000,
            'balance_iqd' => 65_500_000,
        ]);
        $this->seed(DemoHierarchySeeder::class);
        $this->seed(DemoUsersSeeder::class);
        $this->seed(DemoAdvancesSeeder::class);
        $this->seed(DemoAdvancesSeeder::class);

        $this->assertSame(2, EmployeeAdvance::query()->count());
    }

    public function test_accountant_can_cancel_open_advance(): void
    {
        $accountant = $this->userWithRole(Roles::ACCOUNTANT);
        $project = Project::query()->create(['name' => 'Cancel Site']);
        $worker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Cancel Worker',
        ]);
        $advance = EmployeeAdvance::query()->create([
            'worker_id' => $worker->id,
            'project_id' => $project->id,
            'amount_iqd' => 50000,
            'remaining_iqd' => 50000,
            'advanced_on' => '2026-09-10',
            'reason' => 'Cancel me',
            'repayment_method' => EmployeeAdvance::REPAY_CASH,
            'status' => EmployeeAdvance::STATUS_OPEN,
            'entered_by' => $accountant->id,
        ]);

        $this->actingAs($accountant)
            ->post(route('advances.cancel', $advance))
            ->assertRedirect();

        $advance->refresh();
        $this->assertSame(EmployeeAdvance::STATUS_CANCELLED, $advance->status);
        $this->assertSame('0.00', (string) $advance->remaining_iqd);
    }
}
