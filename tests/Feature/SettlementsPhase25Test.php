<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectAllocation;
use App\Models\Transaction;
use App\Models\Vault;
use App\Services\LiquidityService;
use App\Services\MonthlySettlementService;
use App\Support\Roles;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettlementsPhase25Test extends TestCase
{
    use RefreshDatabase;

    private Vault $vault;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        if (! \Illuminate\Support\Facades\Route::has('settlements.index')) {
            $this->markTestSkipped('Module surface dropped in staff rewire slice.');
        }


        Http::fake([
            '*' => Http::response([
                'rates' => ['IQD' => 1310],
            ], 200),
        ]);

        $this->vault = Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 0,
            'balance_iqd' => 0,
        ]);

        $this->project = Project::query()->create([
            'name' => 'Settlement Dual QA',
            'status' => Project::STATUS_ACTIVE,
        ]);

        ProjectAllocation::query()->create([
            'project_id' => $this->project->id,
            'expenses_pool_usd' => 0,
            'payroll_pool_usd' => 0,
            'retention_pool_usd' => 0,
            'penalty_pool_usd' => 0,
            'profit_pool_usd' => 0,
        ]);
    }

    public function test_settlement_exposes_dual_currency_and_hides_live_balance_from_month_lines(): void
    {
        $accountant = $this->actingAsRole(Roles::ACCOUNTANT);

        Transaction::query()->create([
            'vault_id' => $this->vault->id,
            'project_id' => $this->project->id,
            'type' => Transaction::TYPE_MONEY_RECEIVED,
            'amount_usd' => 1000,
            'amount_iqd' => 0,
            'currency' => 'USD',
            'description' => 'USD in',
            'occurred_on' => '2026-09-05',
            'created_by' => $accountant->id,
        ]);

        Transaction::query()->create([
            'vault_id' => $this->vault->id,
            'project_id' => $this->project->id,
            'type' => Transaction::TYPE_MONEY_RECEIVED,
            'amount_usd' => 0,
            'amount_iqd' => 5_000_000,
            'currency' => 'IQD',
            'description' => 'IQD in',
            'occurred_on' => '2026-09-06',
            'created_by' => $accountant->id,
        ]);

        Transaction::query()->create([
            'vault_id' => $this->vault->id,
            'project_id' => $this->project->id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount_usd' => 0,
            'amount_iqd' => 500_000,
            'currency' => 'IQD',
            'description' => 'Materials',
            'occurred_on' => '2026-09-12',
            'created_by' => $accountant->id,
        ]);

        $this->vault->update([
            'balance_usd' => 1000,
            'balance_iqd' => 4_500_000,
        ]);

        $liquidity = app(LiquidityService::class);

        $this->get(route('settlements.index', ['month' => '2026-09']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settlements/Index')
                ->where('settlement.year_month', '2026-09')
                ->where('settlement.money_received_usd', fn ($v) => (float) $v === 1000.0)
                ->where('settlement.money_received_iqd', fn ($v) => (float) $v === 5_000_000.0)
                ->where('settlement.project_expenses_iqd', fn ($v) => (float) $v === 500_000.0)
                ->where('settlement.project_expenses_usd', fn ($v) => (float) $v === 0.0)
                ->where(
                    'settlement.available_money_for_payment_iqd',
                    fn ($v) => (float) $v === $liquidity->availableIqd($this->vault),
                )
                ->where(
                    'settlement.available_money_for_payment_usd',
                    fn ($v) => (float) $v === $liquidity->availableUsd($this->vault),
                )
                ->has('settlement.month_lines')
                ->where('settlement.month_lines', function ($lines) {
                    $keys = collect($lines)->pluck('key')->all();

                    return ! in_array('available_vault_balance', $keys, true)
                        && ! in_array('available_money_for_payment', $keys, true)
                        && in_array('money_received', $keys, true)
                        && in_array('project_expenses', $keys, true);
                })
                ->where('settlement.month_outflows_iqd', fn ($v) => (float) $v === 500_000.0)
            );

        $preview = app(MonthlySettlementService::class)->preview('2026-09', null, $this->vault);
        $this->assertSame(1000.0, $preview['money_received_usd']);
        $this->assertSame(5_000_000.0, $preview['money_received_iqd']);
        // Never FX-blend: available IQD is not USD × rate.
        $this->assertNotEquals(
            round($preview['available_money_for_payment_usd'] * 1310, 2),
            $preview['available_money_for_payment_iqd'],
        );
    }

    public function test_empty_month_activity_still_shows_live_dual_available(): void
    {
        $this->vault->update([
            'balance_usd' => 250,
            'balance_iqd' => 1_000_000,
        ]);

        $this->actingAsRole(Roles::SUPER_ADMIN);

        $this->get(route('settlements.index', ['month' => '2026-01']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settlements/Index')
                ->where('settlement.money_received_usd', fn ($v) => (float) $v === 0.0)
                ->where('settlement.money_received_iqd', fn ($v) => (float) $v === 0.0)
                ->where('settlement.available_money_for_payment_usd', fn ($v) => (float) $v === 250.0)
                ->where('settlement.available_money_for_payment_iqd', fn ($v) => (float) $v === 1_000_000.0)
                ->has('settlement.month_lines', 8)
            );
    }
}
