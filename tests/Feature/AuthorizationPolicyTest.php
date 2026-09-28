<?php

namespace Tests\Feature;

use App\Models\Payout;
use App\Models\Project;
use App\Models\Vault;
use App\Models\Worker;
use App\Support\Roles;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_any_project_and_worker(): void
    {
        $admin = $this->userWithRole(Roles::SUPER_ADMIN);

        $projectA = Project::query()->create(['name' => 'Admin Project A']);
        $projectB = Project::query()->create(['name' => 'Admin Project B']);
        $workerA = Worker::query()->create(['project_id' => $projectA->id, 'name' => 'Worker A']);
        $workerB = Worker::query()->create(['project_id' => $projectB->id, 'name' => 'Worker B']);

        $this->actingAs($admin)->get(route('projects.show', $projectA))->assertOk();
        $this->actingAs($admin)->get(route('projects.show', $projectB))->assertOk();
        $this->actingAs($admin)->get(route('workers.show', $workerA))->assertOk();
        $this->actingAs($admin)->get(route('workers.show', $workerB))->assertOk();
        $this->actingAs($admin)->get(route('workers.index'))->assertOk();
        $this->actingAs($admin)->get(route('dashboards.vault'))->assertOk();
    }

    public function test_stock_manager_cannot_access_business_modules(): void
    {
        $project = Project::query()->create(['name' => 'Site']);
        $worker = Worker::query()->create(['project_id' => $project->id, 'name' => 'Crew']);
        $stock = $this->userWithRole(Roles::STOCK_MANAGER);

        $this->actingAs($stock)->get(route('projects.show', $project))->assertForbidden();
        $this->actingAs($stock)->get(route('projects.index'))->assertForbidden();
        $this->actingAs($stock)->get(route('workers.show', $worker))->assertForbidden();
        $this->actingAs($stock)->get(route('workers.index'))->assertForbidden();
        $this->actingAs($stock)->get(route('projects.create'))->assertForbidden();
        $this->actingAs($stock)->get(route('dashboards.vault'))->assertForbidden();
        $this->actingAs($stock)->get(route('dashboards.payroll'))->assertForbidden();
        $this->actingAs($stock)->get(route('attendance.index'))->assertForbidden();
        $this->actingAs($stock)->get(route('payouts.index'))->assertForbidden();
        $this->actingAs($stock)->get(route('dashboard'))->assertOk();
    }

    public function test_accountant_can_manage_payouts_and_vault_but_not_create_workers(): void
    {
        $accountant = $this->userWithRole(Roles::ACCOUNTANT);
        Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 1000,
            'balance_iqd' => 1310000,
        ]);
        $project = Project::query()->create(['name' => 'Finance Site']);
        $worker = Worker::query()->create(['project_id' => $project->id, 'name' => 'Paid Worker']);
        $payout = Payout::query()->create([
            'project_id' => $project->id,
            'worker_id' => $worker->id,
            'vault_id' => Vault::query()->first()->id,
            'category' => Payout::CATEGORY_PAYROLL,
            'amount_usd' => 50,
            'amount_iqd' => 65500,
            'status' => Payout::STATUS_PENDING,
        ]);

        $this->actingAs($accountant)->get(route('dashboards.vault'))->assertOk();
        $this->actingAs($accountant)->get(route('payouts.index'))->assertOk();
        $this->actingAs($accountant)->get(route('payouts.show', $payout))->assertOk();
        $this->actingAs($accountant)->get(route('payouts.create'))->assertOk();

        $this->actingAs($accountant)->get(route('workers.create'))->assertForbidden();
        $this->actingAs($accountant)->get(route('projects.create'))->assertForbidden();
    }

    public function test_boss_contractor_has_financial_visibility_and_ops_but_not_payout_create(): void
    {
        $boss = $this->userWithRole(Roles::BOSS_CONTRACTOR);
        Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 1000,
            'balance_iqd' => 1310000,
        ]);
        $project = Project::query()->create(['name' => 'Site Ops']);
        $worker = Worker::query()->create(['project_id' => $project->id, 'name' => 'Crew']);

        $this->actingAs($boss)->get(route('workers.index'))->assertOk();
        $this->actingAs($boss)->get(route('workers.show', $worker))->assertOk();
        $this->actingAs($boss)->get(route('workers.create'))->assertOk();
        $this->actingAs($boss)->get(route('projects.create'))->assertOk();
        $this->actingAs($boss)->get(route('attendance.index'))->assertOk();
        $this->actingAs($boss)->get(route('dashboards.vault'))->assertOk();
        $this->actingAs($boss)->get(route('dashboards.payroll'))->assertOk();
        $this->actingAs($boss)->get(route('payouts.index'))->assertOk();
        $this->actingAs($boss)->get(route('retention-holds.index'))->assertOk();

        $this->actingAs($boss)->get(route('payouts.create'))->assertForbidden();
        $this->actingAs($boss)->get(route('backups.index'))->assertForbidden();
        $this->actingAs($boss)->get(route('audit.index'))->assertForbidden();
    }

    public function test_stock_manager_cannot_view_or_approve_payouts(): void
    {
        $vault = Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 1000,
            'balance_iqd' => 1310000,
        ]);
        $project = Project::query()->create(['name' => 'Pay Site']);
        $crew = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Crew',
        ]);
        $payout = Payout::query()->create([
            'project_id' => $project->id,
            'worker_id' => $crew->id,
            'vault_id' => $vault->id,
            'category' => Payout::CATEGORY_PAYROLL,
            'amount_usd' => 40,
            'amount_iqd' => 52400,
            'status' => Payout::STATUS_PENDING,
        ]);

        $stock = $this->userWithRole(Roles::STOCK_MANAGER);

        $this->actingAs($stock)->get(route('payouts.show', $payout))->assertForbidden();
        $this->actingAs($stock)->post(route('payouts.approve', $payout))->assertForbidden();
    }
}
