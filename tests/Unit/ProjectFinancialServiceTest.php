<?php

namespace Tests\Unit;

use App\Models\Payout;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\Vault;
use App\Models\Worker;
use App\Services\ExchangeRateService;
use App\Services\ProjectFinancialService;
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

        app(ExchangeRateService::class)->override(1310.0, 'Phase 5 test rate');

        $this->service = app(ProjectFinancialService::class);
    }

    public function test_summary_math_remaining_and_net_position(): void
    {
        // Known deposit ledger IQD (avoid FX float surprises in assertion)
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

        // Other (penalty) approved payout
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

        // Expenses payout must NOT affect project_expenses (Phase 6 stub)
        Payout::query()->create([
            'vault_id' => $this->vault->id,
            'project_id' => $this->project->id,
            'category' => Payout::CATEGORY_EXPENSES,
            'amount_usd' => 50,
            'amount_iqd' => 65_500,
            'exchange_rate' => 1310,
            'status' => Payout::STATUS_APPROVED,
        ]);

        // Pending payroll ignored
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
        $this->assertSame(0.0, $summary['project_expenses_iqd']);
        $this->assertSame(0.0, $summary['material_cost_iqd']);
        $this->assertSame(131_000.0, $summary['payroll_cost_iqd']);
        $this->assertSame(13_100.0, $summary['other_expenses_iqd']);
        $this->assertSame(86_900_000.0, $summary['remaining_vs_contract_iqd']); // 100M - 13.1M
        $this->assertSame(12_955_900.0, $summary['net_position_iqd']); // 13.1M - 131k - 13.1k
        $this->assertSame('IQD', $summary['currency']);
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

        $this->assertGreaterThan($beforeUsd, (float) $this->vault->fresh()->balance_usd);
        $this->assertSame(1_310_000.0, $this->service->moneyReceivedIqd($this->project));

        $this->assertDatabaseHas('transactions', [
            'project_id' => $this->project->id,
            'type' => Transaction::TYPE_DEPOSIT,
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
}
