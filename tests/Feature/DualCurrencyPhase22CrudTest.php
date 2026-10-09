<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Payout;
use App\Models\Project;
use App\Models\ProjectAllocation;
use App\Models\Transaction;
use App\Models\Vault;
use App\Models\Worker;
use App\Services\VaultService;
use App\Support\DualCurrency;
use App\Support\Roles;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DualCurrencyPhase22CrudTest extends TestCase
{
    use RefreshDatabase;

    private Vault $vault;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        if (! \Illuminate\Support\Facades\Route::has('workers.classify')) {
            $this->markTestSkipped('Module surface dropped in staff rewire slice.');
        }



        $this->vault = Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 0,
            'balance_iqd' => 0,
        ]);

        $this->project = Project::query()->create([
            'name' => 'Mayorca Zhako',
            'contract_value_iqd' => 100_000_000,
        ]);

        // Seed USD cash + pools (Qasa single-leg — IQD unused stays 0).
        app(VaultService::class)->deposit($this->project, 10000, $this->vault);
        // Seed native IQD cash without inventing USD.
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
            DualCurrency::IQD,
            5_000_000,
        );
        $this->vault->refresh();
    }

    public function test_vault_money_in_out_are_single_leg_and_soft_delete_rebuilds(): void
    {
        $accountant = $this->actingAsRole(Roles::ACCOUNTANT);
        $writes = app(\App\Services\VaultLedgerWriteService::class);

        $txn = $writes->create([
            'direction' => 'in',
            'currency' => 'USD',
            'amount' => 250,
            'occurred_on' => now()->toDateString(),
            'description' => 'Money In test',
            'project_id' => $this->project->id,
        ], $accountant);

        $this->assertSame('250.00', (string) $txn->amount_usd);
        $this->assertSame('0.00', (string) $txn->amount_iqd);

        $beforeUsd = (float) $this->vault->fresh()->balance_usd;

        $writes->softDelete($txn, $accountant);

        $this->assertSoftDeleted('transactions', ['id' => $txn->id]);
        $this->assertEqualsWithDelta($beforeUsd - 250, (float) $this->vault->fresh()->balance_usd, 0.01);

        // Legacy ledger HTTP UI is gone.
        $this->get(route('vault.transactions'))->assertRedirect('/dashboards/vault');
        $this->get(route('vault.transactions.create'))->assertRedirect('/dashboards/vault');
    }

    public function test_person_classify_staff_vs_worker_authz(): void
    {
        $boss = $this->actingAsRole(Roles::BOSS_CONTRACTOR);
        $person = Worker::query()->create(['name' => 'Aland']);

        $this->assertTrue($person->isUnclassified());

        $this->actingAs($boss)
            ->post(route('workers.classify', $person), [
                'labor_kind' => 'staff',
                'rate_unit' => 'm2',
                'rate_currency' => 'USD',
                'unit_rate' => 12.5,
            ])
            ->assertRedirect();

        $person->refresh();
        $this->assertTrue($person->isStaff());
        $this->assertSame('12.5000', (string) $person->unit_rate);

        $this->actingAs($boss)
            ->post(route('workers.classify', $person), [
                'labor_kind' => 'worker',
                'monthly_salary_usd' => 800,
                'monthly_salary_iqd' => 0,
            ])
            ->assertRedirect();

        $this->assertTrue($person->fresh()->isEmployee());

        $stock = $this->userWithRole(Roles::STOCK_MANAGER);
        $this->actingAs($stock)
            ->post(route('workers.classify', $person), ['labor_kind' => 'staff'])
            ->assertForbidden();
    }

    public function test_expense_dual_currency_and_pay_ability_hold_approve(): void
    {
        $accountant = $this->actingAsRole(Roles::ACCOUNTANT);

        $this->post(route('expenses.store'), [
            'project_id' => $this->project->id,
            'category' => Expense::CATEGORY_MATERIALS,
            'currency' => 'IQD',
            'amount' => 100_000,
            'expense_date' => now()->toDateString(),
            'supplier' => 'Rayan Supply',
            'payment_method' => Expense::PAYMENT_CASH,
        ])->assertRedirect();

        $expense = Expense::query()->where('supplier', 'Rayan Supply')->first();
        $this->assertNotNull($expense);
        $this->assertSame('IQD', $expense->currency);
        $this->assertSame('100000.00', (string) $expense->amount_iqd);
        $this->assertSame('0.00', (string) $expense->amount_usd);
        $this->assertSame(Expense::STATUS_PENDING, $expense->approval_status);

        $this->actingAs($accountant)
            ->post(route('expenses.hold', $expense), ['notes' => 'Wait for client advance'])
            ->assertRedirect();

        $this->assertSame(Expense::STATUS_HELD, $expense->fresh()->approval_status);

        $beforeIqd = (float) $this->vault->fresh()->balance_iqd;

        $this->actingAs($accountant)
            ->post(route('expenses.approve', $expense))
            ->assertRedirect();

        $expense->refresh();
        $this->assertSame(Expense::STATUS_APPROVED, $expense->approval_status);
        $this->assertNotNull($expense->transaction_id);

        $txn = Transaction::query()->find($expense->transaction_id);
        $this->assertSame('100000.00', (string) $txn->amount_iqd);
        $this->assertSame('0.00', (string) $txn->amount_usd);
        $this->assertEqualsWithDelta($beforeIqd - 100_000, (float) $this->vault->fresh()->balance_iqd, 0.01);
    }

    public function test_expense_approve_blocked_when_available_cash_insufficient(): void
    {
        $accountant = $this->actingAsRole(Roles::ACCOUNTANT);

        $this->post(route('expenses.store'), [
            'project_id' => $this->project->id,
            'category' => Expense::CATEGORY_MATERIALS,
            'currency' => 'IQD',
            'amount' => 9_000_000,
            'expense_date' => now()->toDateString(),
        ]);

        // Create succeeds only if assertCanPay passes at create — with 5M IQD it should fail.
        $this->assertNull(Expense::query()->where('amount_iqd', 9_000_000)->first());
    }

    public function test_payout_currency_selector_and_hold_gate(): void
    {
        $accountant = $this->actingAsRole(Roles::ACCOUNTANT);
        $worker = Worker::query()->create([
            'name' => 'Hunar',
            'labor_kind' => Worker::LABOR_KIND_WORKER,
            'project_id' => $this->project->id,
            'monthly_salary_usd' => 500,
        ]);

        $this->post(route('payouts.store'), [
            'project_id' => $this->project->id,
            'worker_id' => $worker->id,
            'category' => Payout::CATEGORY_PAYROLL,
            'currency' => 'USD',
            'amount' => 200,
            'retention_holdback' => 0,
        ])->assertRedirect();

        $payout = Payout::query()->where('worker_id', $worker->id)->first();
        $this->assertSame('USD', $payout->currency);
        $this->assertSame('200.00', (string) $payout->amount_usd);
        $this->assertSame('0.00', (string) $payout->amount_iqd);

        $this->actingAs($accountant)
            ->post(route('payouts.hold', $payout), ['notes' => 'Cash tight'])
            ->assertRedirect();

        $this->assertSame(Payout::STATUS_HELD, $payout->fresh()->status);

        $this->actingAs($accountant)
            ->post(route('payouts.approve', $payout))
            ->assertRedirect();

        $this->assertSame(Payout::STATUS_APPROVED, $payout->fresh()->status);
    }

    public function test_boss_cannot_approve_expense_or_manage_ledger(): void
    {
        $boss = $this->actingAsRole(Roles::BOSS_CONTRACTOR);
        $accountant = $this->userWithRole(Roles::ACCOUNTANT);

        $expense = Expense::query()->create([
            'project_id' => $this->project->id,
            'vault_id' => $this->vault->id,
            'category' => Expense::CATEGORY_FUEL,
            'currency' => 'IQD',
            'amount_iqd' => 1000,
            'amount_usd' => 0,
            'expense_date' => now()->toDateString(),
            'approval_status' => Expense::STATUS_PENDING,
            'created_by' => $accountant->id,
        ]);

        $this->actingAs($boss)
            ->post(route('expenses.approve', $expense))
            ->assertForbidden();

        $this->actingAs($boss)
            ->post(route('vault.transactions.store'), [
                'direction' => 'in',
                'currency' => 'USD',
                'amount' => 10,
                'occurred_on' => now()->toDateString(),
                'description' => 'Boss blocked',
            ])
            ->assertForbidden();
    }

    public function test_client_advance_records_money_in_and_retention(): void
    {
        $this->markTestSkipped('Legacy /client-advances UI removed; client money-in is vault advance (سلفە).');
    }

    public function test_deposit_no_longer_fx_fills_unused_currency(): void
    {
        $vault = Vault::query()->create([
            'name' => 'Isolation Vault',
            'balance_usd' => 0,
            'balance_iqd' => 0,
        ]);
        $project = Project::query()->create(['name' => 'Isolation']);

        $result = app(VaultService::class)->deposit($project, 100, $vault);
        $this->assertSame(100.0, $result['amount_usd']);
        $this->assertSame(0.0, $result['amount_iqd']);
        $this->assertSame('100.00', (string) $vault->fresh()->balance_usd);
        $this->assertSame('0.00', (string) $vault->fresh()->balance_iqd);

        $allocation = ProjectAllocation::query()->where('project_id', $project->id)->first();
        $this->assertSame('45.00', (string) $allocation->expenses_pool_usd);
    }
}
