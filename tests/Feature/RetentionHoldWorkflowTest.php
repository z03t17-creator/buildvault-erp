<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectAllocation;
use App\Models\RetentionHold;
use App\Models\User;
use App\Models\Vault;
use App\Models\Worker;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetentionHoldWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_surfaces_matured_hold_alerts(): void
    {
        $user = $this->userWithRole();
        $vault = Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 5000,
            'balance_iqd' => 6550000,
        ]);
        $project = Project::query()->create(['name' => 'Alert Site']);
        $worker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Alert Worker',
        ]);

        RetentionHold::query()->create([
            'vault_id' => $vault->id,
            'project_id' => $project->id,
            'worker_id' => $worker->id,
            'amount_usd' => 90,
            'hold_start' => now()->subMonths(7)->toDateString(),
            'maturity_date' => now()->subWeek()->toDateString(),
            'status' => RetentionHold::STATUS_MATURED,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('maturedHolds', 1)
            ->where('alerts.maturedRetentionCount', 1));
    }

    public function test_release_endpoint_returns_funds_to_payroll(): void
    {
        $user = $this->userWithRole();
        $vault = Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 5000,
            'balance_iqd' => 6550000,
        ]);
        $project = Project::query()->create(['name' => 'Release Site']);
        ProjectAllocation::query()->create([
            'project_id' => $project->id,
            'expenses_pool_usd' => 0,
            'payroll_pool_usd' => 500,
            'retention_pool_usd' => 0,
            'penalty_pool_usd' => 0,
            'profit_pool_usd' => 0,
        ]);
        $worker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Release Worker',
        ]);

        $hold = RetentionHold::query()->create([
            'vault_id' => $vault->id,
            'project_id' => $project->id,
            'worker_id' => $worker->id,
            'amount_usd' => 120,
            'hold_start' => now()->subMonths(7)->toDateString(),
            'maturity_date' => now()->subWeek()->toDateString(),
            'status' => RetentionHold::STATUS_MATURED,
        ]);

        $response = $this->actingAs($user)
            ->post(route('retention-holds.release', $hold));

        $response->assertRedirect();
        $this->assertSame(RetentionHold::STATUS_RELEASED, $hold->fresh()->status);
        $this->assertSame(
            '620.00',
            (string) ProjectAllocation::query()->where('project_id', $project->id)->value('payroll_pool_usd'),
        );
    }
}
