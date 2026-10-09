<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Project;
use App\Models\ProjectAllocation;
use App\Models\Transaction;
use App\Models\Vault;
use App\Services\ExpenseService;
use App\Services\ProjectFinancialService;
use App\Services\VaultLedgerService;
use App\Services\VaultService;
use App\Support\Roles;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VaultLedgerPhase12Test extends TestCase
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

        $this->project = Project::query()->create(['name' => 'Ledger Phase 12']);
        ProjectAllocation::query()->create([
            'project_id' => $this->project->id,
            'expenses_pool_usd' => 0,
            'payroll_pool_usd' => 0,
            'retention_pool_usd' => 0,
            'penalty_pool_usd' => 0,
            'profit_pool_usd' => 0,
        ]);
    }

    public function test_ledger_lists_money_received_expense_and_balance_consistency(): void
    {
        $accountant = $this->userWithRole(Roles::ACCOUNTANT);

        app(ProjectFinancialService::class)->recordReceipt($this->project, [
            'amount_iqd' => 13_100_000,
            'received_on' => '2026-09-20',
            'source' => 'Client',
            'reference' => 'INV-12',
            'entered_by' => $accountant->id,
            'vault' => $this->vault,
        ]);

        $this->vault->refresh();
        $this->assertSame(13_100_000.0, (float) $this->vault->balance_iqd);

        $expense = app(ExpenseService::class)->create([
            'project_id' => $this->project->id,
            'vault_id' => $this->vault->id,
            'category' => Expense::CATEGORY_MATERIALS,
            'amount_iqd' => 1_310_000,
            'expense_date' => '2026-09-21',
            'supplier' => 'Supply Co',
            'created_by' => $accountant->id,
        ]);
        app(ExpenseService::class)->approve($expense, $accountant);

        $this->vault->refresh();
        $ledger = app(VaultLedgerService::class);
        $balances = $ledger->balances($this->vault);

        $this->assertTrue($balances['balance_matches_ledger']);
        $this->assertSame((float) $this->vault->balance_iqd, $balances['current_iqd']);
        $this->assertSame($balances['current_iqd'], $balances['ledger_cash_iqd']);

        $this->assertDatabaseHas('transactions', [
            'type' => Transaction::TYPE_MONEY_RECEIVED,
            'amount_iqd' => 13_100_000,
            'reference_code' => 'INV-12',
        ]);
        $this->assertDatabaseHas('transactions', [
            'type' => Transaction::TYPE_EXPENSE,
            'amount_iqd' => 1_310_000,
        ]);
        // IQD Money In is single-leg — no FX pool allocation row.

        $listing = $ledger->listing($this->vault, [
            'type' => Transaction::TYPE_MONEY_RECEIVED,
        ]);
        $moneyInRows = $listing['transactions']->items();
        $this->assertCount(1, $moneyInRows);
        $this->assertSame(Transaction::TYPE_MONEY_RECEIVED, $moneyInRows[0]['type']);
        $this->assertSame('INV-12', $moneyInRows[0]['reference']);

        $cashOnly = $ledger->listing($this->vault, ['include_non_cash' => 0]);
        $this->assertCount(2, $cashOnly['transactions']->items()); // money_received + expense

        // Legacy ledger UI redirected to simple vault home.
        $this->actingAs($accountant)
            ->get(route('vault.transactions'))
            ->assertRedirect('/dashboards/vault');
    }

    public function test_boss_can_view_ledger_stock_manager_blocked(): void
    {
        app(VaultService::class)->deposit($this->project, 100, $this->vault);

        $boss = $this->userWithRole(Roles::BOSS_CONTRACTOR);
        $stock = $this->userWithRole(Roles::STOCK_MANAGER);

        $this->actingAs($boss)->get(route('vault.transactions'))->assertRedirect('/dashboards/vault');
        $this->actingAs($boss)->get(route('dashboards.vault'))->assertOk();
        $this->actingAs($stock)->get(route('vault.transactions'))->assertForbidden();
        $this->actingAs($stock)->get(route('dashboards.vault'))->assertForbidden();
    }

    public function test_monthly_settlement_preview_hook_without_ui(): void
    {
        app(VaultService::class)->deposit(
            $this->project,
            0,
            $this->vault,
            null,
            'Sept deposit',
            Transaction::TYPE_DEPOSIT,
            false,
            '2026-09-05',
            null,
            'IQD',
            1_000_000,
        );

        $preview = app(VaultLedgerService::class)->monthlySettlementPreview('2026-09', $this->vault);

        $this->assertSame('2026-09-01', $preview['from']);
        $this->assertSame('2026-09-30', $preview['to']);
        $this->assertGreaterThan(0, $preview['inflow_iqd']);
        $this->assertSame(0.0, $preview['outflow_iqd']);
        $this->assertSame($preview['opening_iqd'] + $preview['inflow_iqd'], $preview['closing_iqd']);
    }
}
