<?php

namespace Tests\Feature;

use App\Models\MonthlySettlement;
use App\Models\Project;
use App\Models\User;
use App\Models\Vault;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationIntegrityPhase18Test extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const REQUIRED_TABLES = [
        'users',
        'roles',
        'permissions',
        'model_has_roles',
        'model_has_permissions',
        'role_has_permissions',
        'activity_log',
        'vaults',
        'exchange_rates',
        'settings',
        'projects',
        'towers',
        'floors',
        'workers',
        'attendances',
        'transactions',
        'payouts',
        'retention_holds',
        'penalties',
        'documents',
        'imports',
        'import_details',
        'backups',
        'project_allocations',
        'project_receipts',
        'expenses',
        'employee_advances',
        'production_records',
        'suppliers',
        'stock_items',
        'stock_movements',
        'monthly_settlements',
    ];

    public function test_required_tables_exist_after_migrate(): void
    {
        foreach (self::REQUIRED_TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }
    }

    public function test_money_and_phase_columns_exist(): void
    {
        $expectations = [
            'vaults' => ['balance_iqd', 'balance_usd'],
            'transactions' => ['amount_iqd', 'amount_usd', 'exchange_rate', 'occurred_on', 'reference_code'],
            'expenses' => ['amount_iqd', 'amount_usd', 'approval_status'],
            'employee_advances' => ['amount_iqd', 'remaining_iqd'],
            'payouts' => ['amount_iqd', 'amount_usd', 'retention_holdback'],
            'penalties' => ['amount_iqd', 'amount_usd', 'type', 'occurred_on', 'created_by'],
            'project_receipts' => ['amount_iqd', 'amount_usd', 'received_on'],
            'projects' => ['client', 'contract_number', 'contract_value_iqd', 'budget_iqd'],
            'stock_items' => ['purchase_price_iqd', 'quantity', 'min_quantity', 'sku'],
            'stock_movements' => ['type', 'quantity', 'previous_qty', 'new_qty', 'project_id'],
            'workers' => ['manual_ot_hours', 'user_id'],
            'users' => ['locale', 'status', 'phone', 'last_login_at'],
            'retention_holds' => ['pay_period', 'hold_pct', 'released_amount_usd'],
            'monthly_settlements' => [
                'year_month',
                'project_scope_key',
                'money_received_iqd',
                'available_vault_balance_iqd',
                'project_expenses_iqd',
                'payroll_iqd',
                'employee_advances_iqd',
                'insurance_iqd',
                'penalties_iqd',
                'other_expenses_iqd',
                'approved_payments_iqd',
                'available_money_for_payment_iqd',
                'current_vault_iqd',
                'pending_commitments_iqd',
                'reserved_insurance_iqd',
            ],
        ];

        foreach ($expectations as $table => $columns) {
            foreach ($columns as $column) {
                $this->assertTrue(
                    Schema::hasColumn($table, $column),
                    "Missing column {$table}.{$column}"
                );
            }
        }
    }

    public function test_spatie_permission_and_activity_log_ready(): void
    {
        $this->assertTrue(Schema::hasTable('roles'));
        $this->assertTrue(Schema::hasTable('permissions'));
        $this->assertTrue(Schema::hasColumn('activity_log', 'event'));
        $this->assertTrue(Schema::hasColumn('activity_log', 'batch_uuid'));
    }

    public function test_monthly_settlement_rejects_duplicate_null_project_scope(): void
    {
        $vault = Vault::query()->create(['name' => 'Zhako']);

        MonthlySettlement::query()->create([
            'vault_id' => $vault->id,
            'year_month' => '2026-09',
            'project_id' => null,
            'money_received_iqd' => 1000,
        ]);

        $this->expectException(QueryException::class);

        MonthlySettlement::query()->create([
            'vault_id' => $vault->id,
            'year_month' => '2026-09',
            'project_id' => null,
            'money_received_iqd' => 2000,
        ]);
    }

    public function test_monthly_settlement_allows_same_month_different_projects(): void
    {
        $vault = Vault::query()->create(['name' => 'Zhako']);
        $a = Project::query()->create(['name' => 'A', 'status' => Project::STATUS_ACTIVE]);
        $b = Project::query()->create(['name' => 'B', 'status' => Project::STATUS_ACTIVE]);

        MonthlySettlement::query()->create([
            'vault_id' => $vault->id,
            'year_month' => '2026-09',
            'project_id' => $a->id,
            'money_received_iqd' => 100,
        ]);

        MonthlySettlement::query()->create([
            'vault_id' => $vault->id,
            'year_month' => '2026-09',
            'project_id' => $b->id,
            'money_received_iqd' => 200,
        ]);

        MonthlySettlement::query()->create([
            'vault_id' => $vault->id,
            'year_month' => '2026-09',
            'project_id' => null,
            'money_received_iqd' => 300,
        ]);

        $this->assertSame(3, MonthlySettlement::query()->where('year_month', '2026-09')->count());
        $this->assertSame(0, (int) MonthlySettlement::query()->whereNull('project_id')->value('project_scope_key'));
    }

    public function test_core_seed_runs_without_demo_flag(): void
    {
        // RefreshDatabase already migrated; re-seed like production boot (SEED_DEMO=false).
        putenv('SEED_DEMO=false');
        $_ENV['SEED_DEMO'] = 'false';
        $_SERVER['SEED_DEMO'] = 'false';

        Artisan::call('db:seed', ['--force' => true]);

        $this->assertGreaterThanOrEqual(1, User::query()->count());
        $this->assertGreaterThanOrEqual(1, Vault::query()->count());
        $this->assertTrue(Schema::hasTable('roles'));
    }
}
