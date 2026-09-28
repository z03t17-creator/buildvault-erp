<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Project;
use App\Models\StockItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vault;
use App\Models\Worker;
use App\Support\ReportTypes;
use App\Support\Roles;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportsPhase15Test extends TestCase
{
    use RefreshDatabase;

    public function test_reports_index_lists_financial_catalog_for_accountant(): void
    {
        $user = $this->userWithRole(Roles::ACCOUNTANT);

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Index')
                ->has('catalog.financial')
                ->has('catalog.payroll')
                ->has('catalog.stock')
                ->has('catalog.project')
                ->where('can_financial', true)
                ->has('legacy.projects')
            );
    }

    public function test_stock_manager_sees_only_stock_reports(): void
    {
        $stock = $this->userWithRole(Roles::STOCK_MANAGER);

        $this->actingAs($stock)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Index')
                ->missing('catalog.financial')
                ->missing('catalog.payroll')
                ->missing('catalog.project')
                ->has('catalog.stock')
                ->where('can_financial', false)
                ->where('legacy', null)
            );

        $this->actingAs($stock)->get(route('reports.show', ReportTypes::INVENTORY))->assertOk();
        $this->actingAs($stock)->get(route('reports.show', ReportTypes::LOW_STOCK))->assertOk();
        $this->actingAs($stock)->get(route('reports.show', ReportTypes::PROFIT_LOSS))->assertForbidden();
        $this->actingAs($stock)->get(route('reports.export', [
            'type' => ReportTypes::MONTHLY_PAYROLL,
            'format' => 'csv',
        ]))->assertForbidden();
    }

    public function test_project_financial_report_uses_real_db_rows(): void
    {
        $user = $this->userWithRole(Roles::BOSS_CONTRACTOR);
        $project = Project::query()->create([
            'name' => 'Report Site',
            'contract_value_iqd' => 10_000_000,
            'status' => 'active',
        ]);
        $vault = Vault::query()->create(['name' => VaultSeeder::NAME, 'balance_iqd' => 0]);

        Transaction::query()->create([
            'vault_id' => $vault->id,
            'project_id' => $project->id,
            'type' => Transaction::TYPE_MONEY_RECEIVED,
            'occurred_on' => now()->toDateString(),
            'amount_usd' => 0,
            'amount_iqd' => 2_500_000,
            'exchange_rate' => 1310,
            'description' => 'Client payment',
            'created_by' => $user->id,
        ]);

        Expense::query()->create([
            'project_id' => $project->id,
            'vault_id' => $vault->id,
            'category' => Expense::CATEGORY_MATERIALS,
            'amount_iqd' => 400_000,
            'amount_usd' => 0,
            'exchange_rate' => 1310,
            'expense_date' => now()->toDateString(),
            'payment_method' => Expense::PAYMENT_CASH,
            'description' => 'Cement',
            'approval_status' => Expense::STATUS_APPROVED,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('reports.show', [
                'type' => ReportTypes::PROJECT_FINANCIAL,
                'project_id' => $project->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Show')
                ->where('report.type', ReportTypes::PROJECT_FINANCIAL)
                ->where('report.empty', false)
                ->has('report.rows', 1)
                ->where('report.rows.0.money_received_iqd', 2500000)
                ->where('report.rows.0.project_expenses_iqd', 400000)
            );
    }

    public function test_inventory_and_exports_for_stock_manager(): void
    {
        $stock = $this->userWithRole(Roles::STOCK_MANAGER);
        StockItem::query()->create([
            'name' => 'Rebar 12mm',
            'sku' => 'RB-12',
            'category' => 'steel',
            'unit' => 'ton',
            'quantity' => 2,
            'min_quantity' => 5,
            'purchase_price_iqd' => 1_000_000,
        ]);

        $this->actingAs($stock)
            ->get(route('reports.show', ReportTypes::LOW_STOCK))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.type', ReportTypes::LOW_STOCK)
                ->where('report.empty', false)
                ->has('report.rows', 1)
            );

        $csv = $this->actingAs($stock)->get(route('reports.export', [
            'type' => ReportTypes::INVENTORY,
            'format' => 'csv',
        ]));
        $csv->assertOk();
        $this->assertStringContainsString('text/csv', (string) $csv->headers->get('content-type'));

        $xlsx = $this->actingAs($stock)->get(route('reports.export', [
            'type' => ReportTypes::INVENTORY,
            'format' => 'xlsx',
        ]));
        $xlsx->assertOk();
        $xlsx->assertDownload();

        $pdf = $this->actingAs($stock)->get(route('reports.export', [
            'type' => ReportTypes::INVENTORY,
            'format' => 'pdf',
        ]));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_monthly_payroll_report_for_boss(): void
    {
        $boss = $this->userWithRole(Roles::BOSS_CONTRACTOR);
        $project = Project::query()->create(['name' => 'Payroll Site']);
        Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Salary Worker',
            'daily_rate_usd' => 100,
            'overtime_rate_usd' => 10,
        ]);

        $this->actingAs($boss)
            ->get(route('reports.show', [
                'type' => ReportTypes::MONTHLY_PAYROLL,
                'month' => now()->format('Y-m'),
                'project_id' => $project->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.type', ReportTypes::MONTHLY_PAYROLL)
                ->has('report.rows', 1)
                ->where('report.empty', false)
            );
    }

    public function test_unknown_report_type_is_404(): void
    {
        $admin = $this->userWithRole(Roles::SUPER_ADMIN);
        $this->actingAs($admin)->get(route('reports.show', 'attendance'))->assertNotFound();
    }

    public function test_legacy_export_routes_still_work(): void
    {
        $user = $this->userWithRole();
        $project = Project::query()->create(['name' => 'Legacy Excel']);
        Worker::query()->create(['name' => 'Legacy Worker', 'project_id' => $project->id]);

        $this->actingAs($user)->get(route('exports.project', $project))->assertOk();
        $this->actingAs($user)->get(route('exports.worker', [
            'worker' => Worker::query()->first(),
            'month' => now()->format('Y-m'),
        ]))->assertOk();
    }
}
