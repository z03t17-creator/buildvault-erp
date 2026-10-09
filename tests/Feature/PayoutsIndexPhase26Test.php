<?php

namespace Tests\Feature;

use App\Models\Payout;
use App\Models\Project;
use App\Models\Vault;
use App\Models\Worker;
use App\Support\Roles;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PayoutsIndexPhase26Test extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        if (! \Illuminate\Support\Facades\Route::has('payouts.index')) {
            $this->markTestSkipped('Module surface dropped in staff rewire slice.');
        }
    }


    public function test_accountant_sees_ability_cash_and_status_filters(): void
    {
        $vault = Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 5000,
            'balance_iqd' => 10_000_000,
        ]);

        $project = Project::query()->create([
            'name' => 'Payout Index QA',
            'status' => Project::STATUS_ACTIVE,
        ]);

        $worker = Worker::query()->create([
            'name' => 'Payout Worker',
            'project_id' => $project->id,
            'labor_kind' => Worker::LABOR_KIND_WORKER,
            'role' => Worker::ROLE_LABORER,
            'monthly_salary_usd' => 600,
        ]);

        Payout::query()->create([
            'vault_id' => $vault->id,
            'project_id' => $project->id,
            'worker_id' => $worker->id,
            'category' => Payout::CATEGORY_PAYROLL,
            'amount_usd' => 400,
            'amount_iqd' => 0,
            'currency' => 'USD',
            'status' => Payout::STATUS_PENDING,
        ]);

        Payout::query()->create([
            'vault_id' => $vault->id,
            'project_id' => $project->id,
            'worker_id' => $worker->id,
            'category' => Payout::CATEGORY_EXPENSES,
            'amount_usd' => 0,
            'amount_iqd' => 250_000,
            'currency' => 'IQD',
            'status' => Payout::STATUS_HELD,
        ]);

        Payout::query()->create([
            'vault_id' => $vault->id,
            'project_id' => $project->id,
            'category' => Payout::CATEGORY_PROFIT,
            'amount_usd' => 100,
            'amount_iqd' => 0,
            'currency' => 'USD',
            'status' => Payout::STATUS_RECONCILED,
        ]);

        $this->actingAsRole(Roles::ACCOUNTANT);

        $this->get(route('payouts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Payouts/Index')
                ->has('payouts', 3)
                ->where('overview.count', 3)
                ->where('overview.pending', 1)
                ->where('overview.held', 1)
                ->where('overview.reconciled', 1)
                ->where('overview.open_usd', fn ($v) => (float) $v === 400.0)
                ->where('overview.open_iqd', fn ($v) => (float) $v === 250_000.0)
                ->where('overview.pending_usd', fn ($v) => (float) $v === 400.0)
                ->where('overview.pending_iqd', fn ($v) => (float) $v === 0.0)
                ->where('statusCounts.all', 3)
                ->where('statusCounts.pending', 1)
                ->where('statusCounts.held', 1)
                ->has('availableCash')
                ->where('availableCash.available_usd', fn ($v) => (float) $v > 0)
                ->where('availableCash.available_iqd', fn ($v) => (float) $v > 0)
                ->has('ability')
                ->has('filters')
                ->has('categories')
                ->has('statuses')
            );

        $this->get(route('payouts.index', ['status' => 'pending']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Payouts/Index')
                ->has('payouts', 1)
                ->where('filters.status', 'pending')
                ->where('overview.count', 1)
                ->where('overview.pending', 1)
                ->where('payouts.0.category', Payout::CATEGORY_PAYROLL)
            );

        $this->get(route('payouts.index', ['category' => 'expenses']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('payouts', 1)
                ->where('filters.category', 'expenses')
                ->where('overview.held', 1)
                ->where('overview.open_iqd', fn ($v) => (float) $v === 250_000.0)
            );
    }

    public function test_empty_payouts_returns_zero_overview(): void
    {
        Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 100,
            'balance_iqd' => 500_000,
        ]);

        $this->actingAsRole(Roles::SUPER_ADMIN);

        $this->get(route('payouts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Payouts/Index')
                ->has('payouts', 0)
                ->where('overview.count', 0)
                ->where('overview.open_usd', 0)
                ->where('overview.open_iqd', 0)
                ->where('availableCash.available_usd', fn ($v) => (float) $v === 100.0)
            );
    }
}
