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

        $this->actingAs($boss)->get(route('advances.index'))->assertRedirect('/vault/lines/job-pay');
        $this->actingAs($boss)->get(route('advances.create'))->assertRedirect('/vault/lines/job-pay');
        $this->actingAs($boss)
            ->post(route('advances.store'), [
                'worker_id' => $worker->id,
                'project_id' => $project->id,
                'amount_iqd' => 10000,
                'advanced_on' => '2026-09-15',
                'reason' => 'Nope',
                'repayment_method' => EmployeeAdvance::REPAY_CASH,
            ])
            ->assertRedirect(route('vault.lines.job-pay.create'));
    }

    public function test_stock_manager_cannot_access_advances(): void
    {
        $stock = $this->userWithRole(Roles::STOCK_MANAGER);

        $this->actingAs($stock)->get(route('advances.index'))->assertRedirect('/vault/lines/job-pay');
        $this->actingAs($stock)->get(route('advances.create'))->assertRedirect('/vault/lines/job-pay');
    }

    public function test_payroll_dashboard_shows_advances_and_insurance_holdback(): void
    {
        $this->markTestSkipped('Payroll dashboard dropped; advances remain on /advances.');
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
