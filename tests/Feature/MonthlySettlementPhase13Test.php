<?php

namespace Tests\Feature;

use App\Models\Payout;
use App\Models\Project;
use App\Models\ProjectAllocation;
use App\Models\Transaction;
use App\Models\Vault;
use App\Services\ExpenseService;
use App\Services\LiquidityService;
use App\Services\MonthlySettlementService;
use App\Services\ProjectFinancialService;
use App\Services\VaultService;
use App\Models\Expense;
use App\Support\Roles;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MonthlySettlementPhase13Test extends TestCase
{
    use RefreshDatabase;

    private Vault $vault;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->project = Project::query()->create(['name' => 'Settlement Phase 13']);
        ProjectAllocation::query()->create([
            'project_id' => $this->project->id,
            'expenses_pool_usd' => 0,
            'payroll_pool_usd' => 0,
            'retention_pool_usd' => 0,
            'penalty_pool_usd' => 0,
            'profit_pool_usd' => 0,
        ]);
    }

    public function test_available_money_for_payment_equals_liquidity_available(): void
    {
        $accountant = $this->userWithRole(Roles::ACCOUNTANT);

        app(ProjectFinancialService::class)->recordReceipt($this->project, [
            'amount_iqd' => 13_100_000,
            'received_on' => '2026-09-10',
            'source' => 'Client',
            'reference' => 'INV-13',
            'entered_by' => $accountant->id,
            'vault' => $this->vault,
        ]);

        $expense = app(ExpenseService::class)->create([
            'project_id' => $this->project->id,
            'vault_id' => $this->vault->id,
            'category' => Expense::CATEGORY_MATERIALS,
            'amount_iqd' => 1_310_000,
            'expense_date' => '2026-09-12',
            'supplier' => 'Supply Co',
            'created_by' => $accountant->id,
        ]);
        // Keep expense pending so it reduces available via LiquidityService.
        $this->assertSame(Expense::STATUS_PENDING, $expense->approval_status);

        $this->vault->refresh();
        $rate = 1310.0;
        $availableUsd = app(LiquidityService::class)->availableUsd($this->vault);
        $expectedAvailableIqd = round($availableUsd * $rate, 0);

        $preview = app(MonthlySettlementService::class)->preview('2026-09', null, $this->vault);

        $this->assertSame(13_100_000.0, $preview['money_received_iqd']);
        $this->assertSame(0.0, $preview['project_expenses_iqd']); // pending expense not posted to ledger yet
        $this->assertSame($expectedAvailableIqd, $preview['available_vault_balance_iqd']);
        $this->assertSame(
            $expectedAvailableIqd,
            $preview['available_money_for_payment_iqd'],
            'AVAILABLE MONEY FOR PAYMENT must equal live available vault balance',
        );
        $this->assertGreaterThan(0, $preview['pending_commitments_iqd']);
        $this->assertLessThan(
            (float) $this->vault->balance_iqd,
            $preview['available_money_for_payment_iqd'],
        );
    }

    public function test_ability_to_pay_blocks_when_requested_exceeds_available(): void
    {
        app(VaultService::class)->deposit(
            $this->project,
            100,
            $this->vault,
            null,
            'Seed',
            Transaction::TYPE_DEPOSIT,
            true,
            '2026-09-01',
        );

        $service = app(MonthlySettlementService::class);
        $preview = $service->preview('2026-09', null, $this->vault, 999_999_999);

        $this->assertFalse($preview['ability']['allowed']);
        $this->assertGreaterThan(0, $preview['ability']['shortfall_iqd']);
        $this->assertNotEmpty($preview['ability']['reasons']);

        $this->expectException(\InvalidArgumentException::class);
        $service->assertCanAfford(
            (float) $preview['available_money_for_payment_iqd'],
            999_999_999,
        );
    }

    public function test_ability_to_pay_allows_when_within_available(): void
    {
        app(VaultService::class)->deposit(
            $this->project,
            1000,
            $this->vault,
            null,
            'Seed',
            Transaction::TYPE_DEPOSIT,
            true,
            '2026-09-01',
        );

        $service = app(MonthlySettlementService::class);
        $preview = $service->preview('2026-09', null, $this->vault);
        $available = (float) $preview['available_money_for_payment_iqd'];
        $this->assertGreaterThan(0, $available);

        $ability = $service->abilityToPay($available, min(1000, $available));
        $this->assertTrue($ability['allowed']);
        $this->assertSame(0.0, $ability['shortfall_iqd']);
    }

    public function test_settlement_page_rbac_and_month_lines(): void
    {
        $accountant = $this->userWithRole(Roles::ACCOUNTANT);
        app(ProjectFinancialService::class)->recordReceipt($this->project, [
            'amount_iqd' => 5_000_000,
            'received_on' => '2026-09-05',
            'source' => 'Client',
            'reference' => 'R-1',
            'entered_by' => $accountant->id,
            'vault' => $this->vault,
        ]);

        $expense = app(ExpenseService::class)->create([
            'project_id' => $this->project->id,
            'vault_id' => $this->vault->id,
            'category' => Expense::CATEGORY_FUEL,
            'amount_iqd' => 500_000,
            'expense_date' => '2026-09-06',
            'created_by' => $accountant->id,
        ]);
        app(ExpenseService::class)->approve($expense, $accountant);

        $boss = $this->userWithRole(Roles::BOSS_CONTRACTOR);
        $stock = $this->userWithRole(Roles::STOCK_MANAGER);

        $this->actingAs($accountant)
            ->get(route('settlements.index', ['month' => '2026-09']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settlements/Index')
                ->where('settlement.year_month', '2026-09')
                ->where('settlement.money_received_iqd', fn ($v) => (float) $v === 5_000_000.0)
                ->where('settlement.project_expenses_iqd', fn ($v) => (float) $v === 500_000.0)
                ->where('canSave', true)
                ->has('settlement.lines')
            );

        $this->actingAs($boss)
            ->get(route('settlements.index', ['month' => '2026-09']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settlements/Index')
                ->where('canSave', false)
            );

        $this->actingAs($stock)
            ->get(route('settlements.index'))
            ->assertForbidden();

        $this->actingAs($boss)
            ->post(route('settlements.store'), ['month' => '2026-09'])
            ->assertForbidden();
    }

    public function test_accountant_can_save_snapshot_and_block_over_request(): void
    {
        $accountant = $this->userWithRole(Roles::ACCOUNTANT);
        app(VaultService::class)->deposit(
            $this->project,
            500,
            $this->vault,
            null,
            'Seed',
            Transaction::TYPE_DEPOSIT,
            true,
            '2026-09-01',
        );

        $this->actingAs($accountant)
            ->post(route('settlements.store'), [
                'month' => '2026-09',
            ])
            ->assertRedirect(route('settlements.index', ['month' => '2026-09']));

        $this->assertDatabaseHas('monthly_settlements', [
            'year_month' => '2026-09',
            'vault_id' => $this->vault->id,
        ]);

        $this->actingAs($accountant)
            ->from(route('settlements.index', ['month' => '2026-09']))
            ->post(route('settlements.store'), [
                'month' => '2026-09',
                'requested_payout_iqd' => 999_999_999,
            ])
            ->assertRedirect(route('settlements.index', ['month' => '2026-09']))
            ->assertSessionHasErrors('requested_payout_iqd');
    }

    public function test_approved_payments_sum_for_month(): void
    {
        $accountant = $this->userWithRole(Roles::ACCOUNTANT);
        app(VaultService::class)->deposit(
            $this->project,
            2000,
            $this->vault,
            null,
            'Seed',
            Transaction::TYPE_DEPOSIT,
            true,
            '2026-09-01',
        );

        Payout::query()->create([
            'vault_id' => $this->vault->id,
            'project_id' => $this->project->id,
            'category' => Payout::CATEGORY_PAYROLL,
            'amount_usd' => 100,
            'amount_iqd' => 131_000,
            'exchange_rate' => 1310,
            'status' => Payout::STATUS_APPROVED,
            'approved_at' => '2026-09-15 10:00:00',
            'created_by' => $accountant->id,
        ]);

        $preview = app(MonthlySettlementService::class)->preview(
            '2026-09',
            $this->project->id,
            $this->vault,
        );

        $this->assertSame(131_000.0, $preview['approved_payments_iqd']);
    }

    public function test_monthly_settlement_alias_redirects(): void
    {
        $accountant = $this->userWithRole(Roles::ACCOUNTANT);

        $this->actingAs($accountant)
            ->get(route('settlements.alias', ['month' => '2026-09']))
            ->assertRedirect(route('settlements.index', ['month' => '2026-09']));
    }
}
