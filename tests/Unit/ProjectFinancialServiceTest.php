<?php

namespace Tests\Unit;

use App\Models\Expense;
use App\Models\Payout;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\Vault;
use App\Models\Worker;
use App\Services\ExchangeRateService;
use App\Services\ExpenseService;
use App\Services\ProjectFinancialService;
use App\Services\VaultService;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProjectFinancialServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProjectFinancialService $service;

    private Vault $vault;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'https://open.er-api.com/*' => Http::response([
                'result' => 'success',
                'rates' => ['IQD' => 1310],
            ], 200),
        ]);

        $this->vault = Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 0,
            'balance_iqd' => 0,
        ]);

        $this->project = Project::query()->create([
            'name' => 'Finance Site',
            'contract_value_iqd' => 100_000_000,
            'budget_iqd' => 80_000_000,
        ]);

        app(ExchangeRateService::class)->override(1310.0, 'Phase 5/6 test rate');

        $this->service = app(ProjectFinancialService::class);
    }

    public function test_summary_math_remaining_and_net_position(): void
    {
        Transaction::query()->create([
            'vault_id' => $this->vault->id,
            'project_id' => $this->project->id,
            'type' => Transaction::TYPE_DEPOSIT,
            'amount_usd' => 10000,
            'amount_iqd' => 13_100_000,
            'exchange_rate' => 1310,
            'description' => 'Test deposit',
        ]);

        $worker = Worker::query()->create([
            'project_id' => $this->project->id,
            'name' => 'Crew',
        ]);

        Payout::query()->create([
            'vault_id' => $this->vault->id,
            'project_id' => $this->project->id,
            'worker_id' => $worker->id,
            'category' => Payout::CATEGORY_PAYROLL,
            'amount_usd' => 100,
            'amount_iqd' => 131_000,
            'exchange_rate' => 1310,
            'status' => Payout::STATUS_APPROVED,
        ]);

        Payout::query()->create([
            'vault_id' => $this->vault->id,
            'project_id' => $this->project->id,
            'worker_id' => $worker->id,
            'category' => Payout::CATEGORY_PENALTY,
            'amount_usd' => 10,
            'amount_iqd' => 13_100,
            'exchange_rate' => 1310,
            'status' => Payout::STATUS_RECONCILED,
        ]);

        // Legacy expenses-category payout must NOT roll into project_expenses
        Payout::query()->create([
            'vault_id' => $this->vault->id,
            'project_id' => $this->project->id,
            'category' => Payout::CATEGORY_EXPENSES,
            'amount_usd' => 50,
            'amount_iqd' => 65_500,
            'exchange_rate' => 1310,
            'status' => Payout::STATUS_APPROVED,
        ]);

        // Approved Expense module row DOES roll into project_expenses
        Expense::query()->create([
            'project_id' => $this->project->id,
            'vault_id' => $this->vault->id,
            'category' => Expense::CATEGORY_MATERIALS,
            'amount_iqd' => 500_000,
            'amount_usd' => 381.68,
            'exchange_rate' => 1310,
            'expense_date' => now()->toDateString(),
            'approval_status' => Expense::STATUS_APPROVED,
        ]);

        // Pending expense ignored
        Expense::query()->create([
            'project_id' => $this->project->id,
            'vault_id' => $this->vault->id,
            'category' => Expense::CATEGORY_FUEL,
            'amount_iqd' => 100_000,
            'amount_usd' => 76.34,
            'exchange_rate' => 1310,
            'expense_date' => now()->toDateString(),
            'approval_status' => Expense::STATUS_PENDING,
        ]);

        Payout::query()->create([
            'vault_id' => $this->vault->id,
            'project_id' => $this->project->id,
            'worker_id' => $worker->id,
            'category' => Payout::CATEGORY_PAYROLL,
            'amount_usd' => 200,
            'amount_iqd' => 262_000,
            'exchange_rate' => 1310,
            'status' => Payout::STATUS_PENDING,
        ]);

        $summary = $this->service->summary($this->project->fresh());

        $this->assertSame(100_000_000.0, $summary['contract_value_iqd']);
        $this->assertSame(13_100_000.0, $summary['money_received_iqd']);
        $this->assertSame(500_000.0, $summary['project_expenses_iqd']);
        $this->assertSame(0.0, $summary['material_cost_iqd']);
        $this->assertSame(131_000.0, $summary['payroll_cost_iqd']);
        $this->assertSame(13_100.0, $summary['other_expenses_iqd']);
        $this->assertSame(86_900_000.0, $summary['remaining_vs_contract_iqd']);
        // 13.1M − (500k + 0 + 131k + 13.1k) = 12_455_900
        $this->assertSame(12_455_900.0, $summary['net_position_iqd']);
        $this->assertSame('IQD', $summary['currency']);
        $this->assertArrayNotHasKey('project_expenses', $summary['stubs']);
    }

    public function test_record_receipt_updates_vault_and_money_received(): void
    {
        $beforeUsd = (float) $this->vault->fresh()->balance_usd;

        $result = $this->service->recordReceipt($this->project, [
            'amount_iqd' => 1_310_000,
            'received_on' => now()->toDateString(),
            'source' => 'Bank transfer',
            'reference' => 'R-100',
            'notes' => 'Milestone 1',
            'entered_by' => null,
            'vault' => $this->vault,
        ]);

        $this->assertSame(1_310_000.0, (float) $result['receipt']->amount_iqd);
        $this->assertDatabaseHas('project_receipts', [
            'project_id' => $this->project->id,
            'reference' => 'R-100',
            'amount_iqd' => 1_310_000,
        ]);

        $this->assertSame('0.00', (string) $this->vault->fresh()->balance_usd); // IQD receipt does not invent USD
        $this->assertSame('1310000.00', (string) $this->vault->fresh()->balance_iqd);
        $this->assertSame(1_310_000.0, $this->service->moneyReceivedIqd($this->project));

        $this->assertDatabaseHas('transactions', [
            'project_id' => $this->project->id,
            'type' => Transaction::TYPE_MONEY_RECEIVED,
            'amount_iqd' => 1_310_000,
        ]);
    }

    public function test_rejects_non_positive_receipt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->recordReceipt($this->project, [
            'amount_iqd' => 0,
            'received_on' => now()->toDateString(),
        ]);
    }

    public function test_approve_expense_service_posts_vault_outflow_once(): void
    {
        app(VaultService::class)->deposit(
            $this->project,
            0,
            $this->vault,
            null,
            'IQD seed',
            Transaction::TYPE_DEPOSIT,
            false,
            now()->toDateString(),
            null,
            'IQD',
            5_000_000,
        );
        $this->vault->refresh();

        $expenseService = app(ExpenseService::class);
        $expense = $expenseService->create([
            'project_id' => $this->project->id,
            'vault_id' => $this->vault->id,
            'category' => Expense::CATEGORY_TRANSPORTATION,
            'currency' => 'IQD',
            'amount_iqd' => 655_000,
            'expense_date' => now()->toDateString(),
            'supplier' => 'Truck Co',
        ]);

        $before = (float) $this->vault->fresh()->balance_iqd;
        $expenseService->approve($expense);
        $after = (float) $this->vault->fresh()->balance_iqd;

        $this->assertLessThan($before, $after);
        $this->assertSame(1, Transaction::query()
            ->where('type', Transaction::TYPE_EXPENSE)
            ->where('reference_type', $expense->getMorphClass())
            ->where('reference_id', $expense->id)
            ->count());
        $this->assertSame(655_000.0, $this->service->projectExpensesIqd($this->project->fresh()));
    }
}
