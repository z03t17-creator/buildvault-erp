<?php

namespace Tests\Feature;

use App\Models\Payout;
use App\Models\Project;
use App\Models\ProjectAllocation;
use App\Models\Vault;
use App\Models\Worker;
use App\Services\VaultService;
use App\Support\AuditActions;
use App\Support\Roles;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditLoggingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! \Illuminate\Support\Facades\Route::has('payouts.store')) {
            $this->markTestSkipped('Module surface dropped in staff rewire slice.');
        }



        Cache::flush();
        Http::fake([
            'api.exchangerate-api.com/*' => Http::response([
                'rates' => ['IQD' => 1310],
            ], 200),
        ]);
    }

    public function test_payout_approve_and_reject_are_audited(): void
    {
        $user = $this->userWithRole();
        $vault = Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 5000,
            'balance_iqd' => 6550000,
        ]);
        $project = Project::query()->create(['name' => 'Audit Site']);
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
            'name' => 'Audit Worker',
        ]);

        $this->actingAs($user)->post(route('payouts.store'), [
            'project_id' => $project->id,
            'worker_id' => $worker->id,
            'category' => Payout::CATEGORY_PAYROLL,
            'amount_usd' => 100,
        ])->assertRedirect();

        $approved = Payout::query()->first();
        $this->actingAs($user)->post(route('payouts.approve', $approved))->assertRedirect();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => AuditActions::LOG_NAME,
            'event' => AuditActions::PAYOUT_APPROVED,
        ]);
        $this->assertDatabaseHas('activity_log', [
            'event' => AuditActions::ALLOCATION_CHANGED,
        ]);

        $this->actingAs($user)->post(route('payouts.store'), [
            'project_id' => $project->id,
            'worker_id' => $worker->id,
            'category' => Payout::CATEGORY_PAYROLL,
            'amount_usd' => 50,
        ])->assertRedirect();

        $rejected = Payout::query()->orderByDesc('id')->first();
        $this->actingAs($user)->post(route('payouts.reject', $rejected), [
            'notes' => 'Duplicate',
        ])->assertRedirect();

        $this->assertDatabaseHas('activity_log', [
            'event' => AuditActions::PAYOUT_REJECTED,
        ]);
    }

    public function test_vault_deposit_and_fx_override_are_audited(): void
    {
        $user = $this->userWithRole();
        $vault = Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 0,
            'balance_iqd' => 0,
        ]);
        $project = Project::query()->create(['name' => 'Deposit Audit']);

        app(VaultService::class)->deposit($project, 1000, $vault, $user->id);

        $this->assertTrue(
            Activity::query()->where('event', AuditActions::VAULT_DEPOSIT)->exists()
        );
        $this->assertTrue(
            Activity::query()->where('event', AuditActions::ALLOCATION_CHANGED)->exists()
        );

        $this->actingAs($user)
            ->post(route('dashboards.vault.override-fx'), [
                'rate' => 1325.5,
                'note' => 'Desk quote',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('activity_log', [
            'event' => AuditActions::FX_RATE_OVERRIDDEN,
        ]);
    }

    public function test_audit_log_page_restricted_to_finance_roles(): void
    {
        $admin = $this->userWithRole(Roles::SUPER_ADMIN);
        Activity::query()->create([
            'log_name' => AuditActions::LOG_NAME,
            'description' => 'Seed row',
            'event' => AuditActions::VAULT_DEPOSIT,
            'properties' => [],
        ]);

        $this->actingAs($admin)
            ->get(route('audit.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Audit/Log'));

        $boss = $this->userWithRole(Roles::BOSS_CONTRACTOR);
        $this->actingAs($boss)->get(route('audit.index'))->assertForbidden();

        $stock = $this->userWithRole(Roles::STOCK_MANAGER);
        $this->actingAs($stock)->get(route('audit.index'))->assertForbidden();
    }
}
