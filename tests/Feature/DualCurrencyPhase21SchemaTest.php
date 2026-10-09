<?php

namespace Tests\Feature;

use App\Models\ApartmentUnit;
use App\Models\BuildingBlock;
use App\Models\ClientAdvance;
use App\Models\ClientRetentionHold;
use App\Models\Project;
use App\Models\StaffStatement;
use App\Models\Vault;
use App\Models\Worker;
use App\Services\ExchangeRateService;
use App\Services\VaultLedgerService;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DualCurrencyPhase21SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase21_tables_and_columns_exist(): void
    {
        foreach ([
            'staff_statements',
            'client_advances',
            'client_retention_holds',
            'building_blocks',
            'apartment_units',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }

        $this->assertTrue(Schema::hasColumn('workers', 'labor_kind'));
        $this->assertTrue(Schema::hasColumn('workers', 'monthly_salary_iqd'));
        $this->assertTrue(Schema::hasColumn('workers', 'rate_unit'));
        $this->assertTrue(Schema::hasColumn('transactions', 'balance_after_usd'));
        $this->assertTrue(Schema::hasColumn('transactions', 'balance_after_iqd'));
        $this->assertTrue(Schema::hasColumn('transactions', 'deleted_at'));
        $this->assertTrue(Schema::hasColumn('retention_holds', 'amount_iqd'));
        $this->assertTrue(Schema::hasColumn('employee_advances', 'amount_usd'));
        $this->assertTrue(Schema::hasColumn('attendances', 'forfeit_day'));
        $this->assertTrue(Schema::hasColumn('attendances', 'entered_by'));
        $this->assertTrue(Schema::hasColumn('expenses', 'deleted_at'));
        $this->assertTrue(Schema::hasColumn('payouts', 'deleted_at'));
    }

    public function test_person_starts_unclassified_and_user_classifies(): void
    {
        $person = Worker::query()->create([
            'name' => 'Adil Laminate',
        ]);

        $this->assertTrue($person->isUnclassified());
        $this->assertSame(Worker::LABOR_KIND_UNCLASSIFIED, $person->labor_kind);

        $person->classify(Worker::LABOR_KIND_STAFF);
        $this->assertTrue($person->fresh()->isStaff());
        $this->assertNotNull($person->fresh()->classified_at);

        $employee = Worker::query()->create(['name' => 'Office Worker']);
        $employee->classify(Worker::LABOR_KIND_WORKER);
        $this->assertTrue($employee->fresh()->isEmployee());
    }

    public function test_client_advance_and_retention_hold_persist_dual_amounts(): void
    {
        $project = Project::query()->create(['name' => 'Mayorca']);
        $vault = Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 0,
            'balance_iqd' => 0,
        ]);

        $advance = ClientAdvance::query()->create([
            'project_id' => $project->id,
            'vault_id' => $vault->id,
            'client_name' => 'Mayorca Client',
            'amount_usd' => 10000,
            'amount_iqd' => 0,
            'currency' => 'USD',
            'received_on' => '2026-01-01',
        ]);

        $hold = ClientRetentionHold::query()->create([
            'client_advance_id' => $advance->id,
            'project_id' => $project->id,
            'vault_id' => $vault->id,
            'amount_usd' => 1000,
            'amount_iqd' => 0,
            'hold_pct' => 10,
            'maturity_days' => 180,
            'hold_start' => '2026-01-01',
            'maturity_date' => ClientRetentionHold::maturityFrom('2026-01-01'),
        ]);

        $this->assertSame(1000.0, (float) $hold->amount_usd);
        $this->assertSame(0.0, (float) $hold->amount_iqd);
        $this->assertSame('2026-06-30', $hold->maturity_date->toDateString());
    }

    public function test_staff_statement_and_spatial_units(): void
    {
        $project = Project::query()->create(['name' => 'Mayorca']);
        $staff = Worker::query()->create([
            'name' => 'Hunar',
            'labor_kind' => Worker::LABOR_KIND_STAFF,
            'rate_unit' => 'm2',
            'rate_currency' => 'USD',
            'unit_rate' => 12.5,
        ]);

        $stmt = StaffStatement::query()->create([
            'worker_id' => $staff->id,
            'project_id' => $project->id,
            'earned_usd' => 500,
            'earned_iqd' => 0,
            'paid_usd' => 100,
            'retention_held_usd' => 50,
            'advances_usd' => 25,
        ]);
        $stmt->recalculateRemaining();
        $stmt->save();

        $this->assertSame(325.0, (float) $stmt->fresh()->remaining_usd);
        $this->assertSame(0.0, (float) $stmt->fresh()->remaining_iqd);

        $block = BuildingBlock::query()->create([
            'project_id' => $project->id,
            'code' => 'B1',
            'name' => 'Block 1',
        ]);

        $unit = ApartmentUnit::query()->create([
            'project_id' => $project->id,
            'building_block_id' => $block->id,
            'unit_label' => '101',
            'floor_number' => 1,
            'category' => ApartmentUnit::CATEGORY_MDF,
            'assigned_worker_id' => $staff->id,
        ]);

        $this->assertSame('mdf', $unit->category);
        $this->assertFalse($unit->is_company_crew);
    }

    public function test_vault_ledger_balances_do_not_fx_blend(): void
    {
        $vault = Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 100,
            'balance_iqd' => 50_000,
        ]);

        $ledger = app(VaultLedgerService::class);
        $balances = $ledger->balances($vault, 9999.0); // absurd rate must be ignored

        $this->assertSame(100.0, $balances['available_usd']);
        $this->assertSame(50000.0, $balances['available_iqd']);
        // Prove no blend: available_iqd is NOT available_usd * rate
        $this->assertNotEquals(round(100 * 9999), $balances['available_iqd']);
    }

    public function test_explicit_fx_conversion_is_audited(): void
    {
        $fx = app(ExchangeRateService::class);
        $result = $fx->convertExplicit(10, 'USD', 'IQD', 1310, 'test conversion');

        $this->assertSame(13100.0, $result['amount_to']);
        $this->assertDatabaseHas('activity_log', [
            'event' => 'fx.explicit_conversion',
        ]);
    }
}
