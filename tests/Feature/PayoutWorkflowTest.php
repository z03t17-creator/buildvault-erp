<?php

namespace Tests\Feature;

use App\Models\Payout;
use App\Models\Project;
use App\Models\ProjectAllocation;
use App\Models\User;
use App\Models\Vault;
use App\Models\Worker;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PayoutWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Http::fake([
            'api.exchangerate-api.com/*' => Http::response([
                'rates' => ['IQD' => 1310],
            ], 200),
        ]);
    }

    public function test_store_approve_reconcile_happy_path(): void
    {
        $user = $this->userWithRole();
        $vault = Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 5000,
            'balance_iqd' => 6550000,
        ]);
        $project = Project::query()->create(['name' => 'HTTP Payout']);
        ProjectAllocation::query()->create([
            'project_id' => $project->id,
            'expenses_pool_usd' => 2000,
            'payroll_pool_usd' => 2000,
            'retention_pool_usd' => 500,
            'penalty_pool_usd' => 250,
            'profit_pool_usd' => 250,
        ]);
        $worker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Crew',
        ]);

        $this->actingAs($user)
            ->post(route('payouts.store'), [
                'project_id' => $project->id,
                'worker_id' => $worker->id,
                'category' => Payout::CATEGORY_PAYROLL,
                'amount_usd' => 500,
            ])
            ->assertRedirect();

        $payout = Payout::query()->first();
        $this->assertNotNull($payout);
        $this->assertSame(Payout::STATUS_PENDING, $payout->status);

        $this->actingAs($user)
            ->get(route('payouts.show', $payout))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Payouts/Show')
                ->where('payout.id', $payout->id)
            );

        $this->actingAs($user)
            ->post(route('payouts.approve', $payout))
            ->assertRedirect();

        $payout->refresh();
        $this->assertSame(Payout::STATUS_APPROVED, $payout->status);
        $this->assertDatabaseHas('retention_holds', [
            'payout_id' => $payout->id,
            'worker_id' => $worker->id,
        ]);

        $this->actingAs($user)
            ->post(route('payouts.reconcile', $payout))
            ->assertRedirect();

        $this->assertSame(Payout::STATUS_RECONCILED, $payout->fresh()->status);
        $this->assertSame('4550.00', (string) $vault->fresh()->balance_usd); // 5000 - (500 - 50)
    }

    public function test_store_blocked_without_liquidity(): void
    {
        $user = $this->userWithRole();
        Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 100,
            'balance_iqd' => 0,
        ]);
        $project = Project::query()->create(['name' => 'Dry']);
        ProjectAllocation::query()->create([
            'project_id' => $project->id,
            'expenses_pool_usd' => 50,
            'payroll_pool_usd' => 50,
            'retention_pool_usd' => 0,
            'penalty_pool_usd' => 0,
            'profit_pool_usd' => 0,
        ]);

        $this->actingAs($user)
            ->from(route('payouts.create'))
            ->post(route('payouts.store'), [
                'project_id' => $project->id,
                'category' => Payout::CATEGORY_EXPENSES,
                'amount_usd' => 80,
            ])
            ->assertRedirect(route('payouts.create'))
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('payouts', 0);
    }
}
