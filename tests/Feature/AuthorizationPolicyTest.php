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

    public function test_worker_cannot_access_other_projects_or_workers(): void
    {
        $projectOwn = Project::query()->create(['name' => 'Own Site']);
        $projectOther = Project::query()->create(['name' => 'Other Site']);

        $workerUser = $this->userWithRole(Roles::WORKER);
        $ownWorker = Worker::query()->create([
            'project_id' => $projectOwn->id,
            'user_id' => $workerUser->id,
            'name' => 'Own Worker',
        ]);
        $otherWorker = Worker::query()->create([
            'project_id' => $projectOther->id,
            'name' => 'Other Worker',
        ]);

        $this->actingAs($workerUser)->get(route('projects.show', $projectOwn))->assertOk();
        $this->actingAs($workerUser)->get(route('projects.show', $projectOther))->assertForbidden();

        $this->actingAs($workerUser)->get(route('workers.show', $ownWorker))->assertOk();
        $this->actingAs($workerUser)->get(route('workers.show', $otherWorker))->assertForbidden();
        $this->actingAs($workerUser)->get(route('workers.index'))->assertForbidden();

        $this->actingAs($workerUser)->get(route('projects.create'))->assertForbidden();
        $this->actingAs($workerUser)->get(route('dashboards.vault'))->assertForbidden();
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

    public function test_site_engineer_can_manage_workers_but_not_vault_or_payout_create(): void
    {
        $engineer = $this->userWithRole(Roles::SITE_ENGINEER);
        $project = Project::query()->create(['name' => 'Site Ops']);
        $worker = Worker::query()->create(['project_id' => $project->id, 'name' => 'Crew']);

        $this->actingAs($engineer)->get(route('workers.index'))->assertOk();
        $this->actingAs($engineer)->get(route('workers.show', $worker))->assertOk();
        $this->actingAs($engineer)->get(route('workers.create'))->assertOk();
        $this->actingAs($engineer)->get(route('projects.create'))->assertOk();
        $this->actingAs($engineer)->get(route('attendance.index'))->assertOk();

        $this->actingAs($engineer)->get(route('dashboards.vault'))->assertForbidden();
        $this->actingAs($engineer)->get(route('payouts.create'))->assertForbidden();
        $this->actingAs($engineer)->get(route('backups.index'))->assertForbidden();
    }

    public function test_worker_can_view_own_payout_only(): void
    {
        $vault = Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 1000,
            'balance_iqd' => 1310000,
        ]);
        $project = Project::query()->create(['name' => 'Pay Site']);

        $workerUser = $this->userWithRole(Roles::WORKER);
        $ownWorker = Worker::query()->create([
            'project_id' => $project->id,
            'user_id' => $workerUser->id,
            'name' => 'Me',
        ]);
        $otherWorker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Them',
        ]);

        $ownPayout = Payout::query()->create([
            'project_id' => $project->id,
            'worker_id' => $ownWorker->id,
            'vault_id' => $vault->id,
            'category' => Payout::CATEGORY_PAYROLL,
            'amount_usd' => 40,
            'amount_iqd' => 52400,
            'status' => Payout::STATUS_PENDING,
        ]);
        $otherPayout = Payout::query()->create([
            'project_id' => $project->id,
            'worker_id' => $otherWorker->id,
            'vault_id' => $vault->id,
            'category' => Payout::CATEGORY_PAYROLL,
            'amount_usd' => 60,
            'amount_iqd' => 78600,
            'status' => Payout::STATUS_PENDING,
        ]);

        $this->actingAs($workerUser)->get(route('payouts.show', $ownPayout))->assertOk();
        $this->actingAs($workerUser)->get(route('payouts.show', $otherPayout))->assertForbidden();
        $this->actingAs($workerUser)->post(route('payouts.approve', $ownPayout))->assertForbidden();
    }
}
