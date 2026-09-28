<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Project;
use App\Models\ProjectAllocation;
use App\Models\Transaction;
use App\Models\Vault;
use App\Services\ProjectFinancialService;
use App\Services\VaultService;
use App\Support\Roles;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExpensePhase6Test extends TestCase
{
    use RefreshDatabase;

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
            'name' => 'Expense Site',
            'contract_value_iqd' => 100_000_000,
        ]);

        app(VaultService::class)->deposit($this->project, 10000, $this->vault);
        $this->vault->refresh();
    }

    public function test_accountant_can_create_and_approve_expense_updating_financials_and_vault(): void
    {
        $accountant = $this->userWithRole(Roles::ACCOUNTANT);
        $beforeUsd = (float) $this->vault->balance_usd;
        $allocation = ProjectAllocation::query()->where('project_id', $this->project->id)->first();
        $beforePool = (float) $allocation->expenses_pool_usd;

        $this->actingAs($accountant)
            ->post(route('expenses.store'), [
                'project_id' => $this->project->id,
                'category' => Expense::CATEGORY_MATERIALS,
                'amount_iqd' => 1_310_000,
                'expense_date' => now()->toDateString(),
                'supplier' => 'Erbil Supply Co',
                'payment_method' => Expense::PAYMENT_CASH,
                'description' => 'Rebar batch',
            ])
            ->assertRedirect();

        $expense = Expense::query()->where('supplier', 'Erbil Supply Co')->first();
        $this->assertNotNull($expense);
        $this->assertSame(Expense::STATUS_PENDING, $expense->approval_status);
        $this->assertSame('1310000.00', (string) $expense->amount_iqd);
        $this->assertSame($accountant->id, $expense->created_by);

        // Pending does not roll into project financials yet
        $summaryPending = app(ProjectFinancialService::class)->summary($this->project->fresh());
        $this->assertSame(0.0, $summaryPending['project_expenses_iqd']);

        $this->actingAs($accountant)
            ->post(route('expenses.approve', $expense))
            ->assertRedirect();

        $expense->refresh();
        $this->assertSame(Expense::STATUS_APPROVED, $expense->approval_status);
        $this->assertNotNull($expense->transaction_id);

        $this->assertDatabaseHas('transactions', [
            'id' => $expense->transaction_id,
            'type' => Transaction::TYPE_EXPENSE,
            'project_id' => $this->project->id,
            'amount_iqd' => 1_310_000,
            'reference_type' => $expense->getMorphClass(),
            'reference_id' => $expense->id,
        ]);

        $this->vault->refresh();
        $this->assertLessThan($beforeUsd, (float) $this->vault->balance_usd);

        $allocation->refresh();
        $this->assertLessThan($beforePool, (float) $allocation->expenses_pool_usd);

        $summary = app(ProjectFinancialService::class)->summary($this->project->fresh());
        $this->assertSame(1_310_000.0, $summary['project_expenses_iqd']);
        $this->assertArrayNotHasKey('project_expenses', $summary['stubs']);

        // No Payout double-count row
        $this->assertDatabaseMissing('payouts', [
            'project_id' => $this->project->id,
            'category' => 'expenses',
            'amount_iqd' => 1_310_000,
        ]);
    }

    public function test_boss_can_view_but_not_create_or_approve(): void
    {
        $boss = $this->userWithRole(Roles::BOSS_CONTRACTOR);
        $accountant = $this->userWithRole(Roles::ACCOUNTANT);

        $expense = app(\App\Services\ExpenseService::class)->create([
            'project_id' => $this->project->id,
            'vault_id' => $this->vault->id,
            'category' => Expense::CATEGORY_FUEL,
            'amount_iqd' => 131_000,
            'expense_date' => now()->toDateString(),
            'supplier' => 'Boss View',
            'created_by' => $accountant->id,
        ]);

        $this->actingAs($boss)->get(route('expenses.index'))->assertOk();
        $this->actingAs($boss)->get(route('expenses.show', $expense))->assertOk();
        $this->actingAs($boss)->get(route('expenses.create'))->assertForbidden();
        $this->actingAs($boss)
            ->post(route('expenses.store'), [
                'project_id' => $this->project->id,
                'category' => Expense::CATEGORY_FUEL,
                'amount_iqd' => 1000,
                'expense_date' => now()->toDateString(),
            ])
            ->assertForbidden();
        $this->actingAs($boss)->post(route('expenses.approve', $expense))->assertForbidden();
        $this->actingAs($boss)->post(route('expenses.reject', $expense))->assertForbidden();
    }

    public function test_stock_manager_has_no_expense_access(): void
    {
        $stock = $this->userWithRole(Roles::STOCK_MANAGER);
        $expense = Expense::query()->create([
            'project_id' => $this->project->id,
            'vault_id' => $this->vault->id,
            'category' => Expense::CATEGORY_OTHER,
            'amount_iqd' => 1000,
            'amount_usd' => 1,
            'exchange_rate' => 1310,
            'expense_date' => now()->toDateString(),
            'approval_status' => Expense::STATUS_PENDING,
        ]);

        $this->actingAs($stock)->get(route('expenses.index'))->assertForbidden();
        $this->actingAs($stock)->get(route('expenses.show', $expense))->assertForbidden();
        $this->actingAs($stock)->get(route('expenses.create'))->assertForbidden();
        $this->actingAs($stock)
            ->post(route('expenses.store'), [
                'project_id' => $this->project->id,
                'category' => Expense::CATEGORY_OTHER,
                'amount_iqd' => 1000,
                'expense_date' => now()->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_expenses_index_inertia_for_accountant(): void
    {
        $accountant = $this->userWithRole(Roles::ACCOUNTANT);

        $this->actingAs($accountant)
            ->get(route('expenses.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Expenses/Index')
                ->has('expenses')
                ->has('categories')
            );
    }

    public function test_rejected_expense_does_not_affect_financials_or_vault(): void
    {
        $accountant = $this->userWithRole(Roles::ACCOUNTANT);
        $beforeUsd = (float) $this->vault->fresh()->balance_usd;

        $expense = app(\App\Services\ExpenseService::class)->create([
            'project_id' => $this->project->id,
            'vault_id' => $this->vault->id,
            'category' => Expense::CATEGORY_OFFICE,
            'amount_iqd' => 262_000,
            'expense_date' => now()->toDateString(),
            'supplier' => 'Reject Me',
            'created_by' => $accountant->id,
        ]);

        $this->actingAs($accountant)
            ->post(route('expenses.reject', $expense), ['notes' => 'Duplicate'])
            ->assertRedirect();

        $expense->refresh();
        $this->assertSame(Expense::STATUS_REJECTED, $expense->approval_status);
        $this->assertNull($expense->transaction_id);
        $this->assertSame($beforeUsd, (float) $this->vault->fresh()->balance_usd);
        $this->assertSame(0.0, app(ProjectFinancialService::class)->projectExpensesIqd($this->project));
    }
}
