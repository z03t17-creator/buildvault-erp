<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectAllocation;
use App\Models\RetentionHold;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vault;
use App\Models\Worker;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VaultDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_vault_dashboard_renders_dual_currency_and_fx(): void
    {
        Cache::flush();
        Http::fake([
            'api.exchangerate-api.com/*' => Http::response([
                'rates' => ['IQD' => 1325.5],
            ], 200),
        ]);

        $user = User::factory()->create();
        $vault = Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 10000,
            'balance_iqd' => 13255000,
        ]);

        $project = Project::query()->create(['name' => 'Vault Dash']);
        ProjectAllocation::query()->create([
            'project_id' => $project->id,
            'expenses_pool_usd' => 4500,
            'payroll_pool_usd' => 3000,
            'retention_pool_usd' => 1000,
            'penalty_pool_usd' => 500,
            'profit_pool_usd' => 1000,
        ]);

        $worker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Dash Worker',
        ]);

        RetentionHold::query()->create([
            'vault_id' => $vault->id,
            'project_id' => $project->id,
            'worker_id' => $worker->id,
            'amount_usd' => 100,
            'hold_start' => now()->subMonths(2)->toDateString(),
            'maturity_date' => now()->addMonths(4)->toDateString(),
            'status' => RetentionHold::STATUS_HOLDING,
        ]);

        Transaction::query()->create([
            'vault_id' => $vault->id,
            'project_id' => $project->id,
            'type' => Transaction::TYPE_DEPOSIT,
            'amount_usd' => 10000,
            'amount_iqd' => 13255000,
            'exchange_rate' => 1325.5,
            'description' => 'Seed deposit',
        ]);

        $response = $this->actingAs($user)->get(route('dashboards.vault'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboards/Vault')
            ->where('vault.name', VaultSeeder::NAME)
            ->where('vault.balance_usd', 10000)
            ->where('vault.balance_iqd', 13255000)
            ->where('fx.rate', 1325.5)
            ->where('insurance.holding_usd', 100)
            ->has('cashFlow', 30)
            ->has('health', 3)
            ->where('pools.payroll_usd', 3000));
    }

    public function test_refresh_fx_endpoint(): void
    {
        Cache::flush();
        Http::fake([
            'api.exchangerate-api.com/*' => Http::response([
                'rates' => ['IQD' => 1400],
            ], 200),
        ]);

        $user = User::factory()->create();
        Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 0,
            'balance_iqd' => 0,
        ]);

        $response = $this->actingAs($user)
            ->from(route('dashboards.vault'))
            ->post(route('dashboards.vault.refresh-fx'));

        $response->assertRedirect(route('dashboards.vault'));
        $this->assertDatabaseHas('exchange_rates', [
            'base_currency' => 'USD',
            'target_currency' => 'IQD',
            'rate' => 1400,
        ]);
    }
}
