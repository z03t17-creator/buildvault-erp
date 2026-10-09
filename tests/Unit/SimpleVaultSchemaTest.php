<?php

namespace Tests\Unit;

use App\Models\Penalty;
use App\Models\Project;
use App\Models\Staff;
use App\Models\Vault;
use App\Models\VaultLine;
use App\Services\SimpleVaultService;
use App\Support\DualCurrency;
use Carbon\Carbon;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SimpleVaultSchemaTest extends TestCase
{
    use RefreshDatabase;

    private SimpleVaultService $vault;

    private Vault $zhako;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vault = app(SimpleVaultService::class);
        $this->zhako = Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 0,
            'balance_iqd' => 0,
        ]);
    }

    public function test_staff_and_vault_lines_tables_exist_with_staff_fks(): void
    {
        $this->assertTrue(Schema::hasTable('staff'));
        $this->assertTrue(Schema::hasTable('vault_lines'));
        $this->assertTrue(Schema::hasColumn('attendances', 'staff_id'));
        $this->assertFalse(Schema::hasColumn('attendances', 'worker_id'));
        $this->assertTrue(Schema::hasColumn('penalties', 'staff_id'));
        $this->assertFalse(Schema::hasColumn('penalties', 'worker_id'));
        $this->assertTrue(Schema::hasColumn('vault_lines', 'hold_pool'));
        $this->assertTrue(Schema::hasColumn('vault_lines', 'unlock_date'));
    }

    public function test_advance_holds_ten_percent_company_insurance_with_unlock_date(): void
    {
        Carbon::setTestNow('2026-10-05');

        $line = $this->vault->postAdvance([
            'amount' => 1000,
            'currency' => DualCurrency::USD,
            'occurred_on' => '2026-10-05',
            'vault_id' => $this->zhako->id,
        ]);

        $this->assertSame(VaultLine::KIND_ADVANCE, $line->kind);
        $this->assertSame(1000.0, (float) $line->amount);
        $this->assertSame(100.0, (float) $line->hold_amount);
        $this->assertSame(900.0, $line->availablePortion());
        $this->assertSame(VaultLine::HOLD_POOL_COMPANY_INSURANCE, $line->hold_pool);
        $this->assertSame('2027-04-03', $line->unlock_date->toDateString()); // +180 days

        $balances = $this->vault->balances(DualCurrency::USD, '2026-10-05', $this->zhako);
        $this->assertSame(900.0, $balances['available_cash']);
        $this->assertSame(100.0, $balances['company_insurance_held']);
        $this->assertSame(0.0, $balances['staff_owed_held']);

        $afterUnlock = $this->vault->balances(DualCurrency::USD, '2027-04-03', $this->zhako);
        $this->assertSame(1000.0, $afterUnlock['available_cash']);
        $this->assertSame(0.0, $afterUnlock['company_insurance_held']);
    }

    public function test_job_pay_holds_ten_percent_owed_to_time_staff(): void
    {
        $staff = Staff::query()->create([
            'name' => 'Karwan Laminate',
            'kind' => Staff::KIND_TIME,
            'trade' => 'laminate',
        ]);

        $line = $this->vault->postJobPay([
            'staff_id' => $staff->id,
            'amount' => 500,
            'currency' => DualCurrency::IQD,
            'occurred_on' => '2026-10-01',
            'vault_id' => $this->zhako->id,
        ]);

        $this->assertSame(VaultLine::KIND_JOB_PAY, $line->kind);
        $this->assertSame(50.0, (float) $line->hold_amount);
        $this->assertSame(VaultLine::HOLD_POOL_STAFF_OWED, $line->hold_pool);
        $this->assertSame('2027-03-30', $line->unlock_date->toDateString());

        // Seed available cash via advance first so job_pay can leave
        $this->vault->postAdvance([
            'amount' => 1000,
            'currency' => DualCurrency::IQD,
            'occurred_on' => '2026-09-01',
            'vault_id' => $this->zhako->id,
        ]);

        $balances = $this->vault->balances(DualCurrency::IQD, '2026-10-01', $this->zhako);
        // advance 900 available + company 100 held; job_pay removes 450 from available, 50 staff owed
        $this->assertSame(450.0, $balances['available_cash']);
        $this->assertSame(100.0, $balances['company_insurance_held']);
        $this->assertSame(50.0, $balances['staff_owed_held']);

        // Early confirm (before unlock) is allowed when the user pays.
        $released = $this->vault->releaseStaffHold($line, '2026-10-15');
        $this->assertNotNull($released->hold_released_at);

        $afterEarly = $this->vault->balances(DualCurrency::IQD, '2026-10-15', $this->zhako);
        $this->assertSame(0.0, $afterEarly['staff_owed_held']);
        // Available unchanged by staff hold release (never was Available Cash)
        $this->assertSame(450.0, $afterEarly['available_cash']);
    }

    public function test_salary_without_penalty_pays_full_monthly(): void
    {
        $staff = Staff::query()->create([
            'name' => 'Sara Office',
            'kind' => Staff::KIND_SALARY,
            'trade' => 'office',
            'monthly_salary' => 600,
            'currency' => DualCurrency::USD,
        ]);

        $due = $this->vault->salaryDueFor($staff, '2026-10-01');
        $this->assertSame(600.0, $due);

        $line = $this->vault->postSalary([
            'staff_id' => $staff->id,
            'month' => '2026-10-01',
            'vault_id' => $this->zhako->id,
        ]);

        $this->assertSame(VaultLine::KIND_SALARY, $line->kind);
        $this->assertSame(600.0, (float) $line->amount);
        $this->assertSame(0.0, (float) $line->hold_amount);
        $this->assertNull($line->hold_pool);
        $this->assertNull($line->unlock_date);
    }

    public function test_salary_with_forfeit_day_penalty_cuts_one_day(): void
    {
        $project = Project::query()->create(['name' => 'Mayorca']);
        $staff = Staff::query()->create([
            'name' => 'Dlir Site',
            'kind' => Staff::KIND_SALARY,
            'trade' => 'MDF',
            'monthly_salary' => 310,
            'currency' => DualCurrency::USD,
        ]);

        // October 2026 has 31 days → daily = 10
        Penalty::query()->create([
            'staff_id' => $staff->id,
            'project_id' => $project->id,
            'type' => Penalty::TYPE_FORFEIT_DAY,
            'reason' => 'Late > 30 minutes',
            'amount_usd' => 10,
            'amount_iqd' => 0,
            'currency' => DualCurrency::USD,
            'occurred_on' => '2026-10-12',
            'status' => Penalty::STATUS_APPLIED,
        ]);

        $due = $this->vault->salaryDueFor($staff, '2026-10-01');
        $this->assertSame(300.0, $due);

        $line = $this->vault->postSalary([
            'staff_id' => $staff->id,
            'month' => '2026-10-01',
            'vault_id' => $this->zhako->id,
        ]);

        $this->assertSame(300.0, (float) $line->amount);
        $this->assertSame(0.0, (float) $line->hold_amount);
    }

    public function test_expense_has_no_hold(): void
    {
        $line = $this->vault->postExpense([
            'amount' => 75.5,
            'currency' => DualCurrency::IQD,
            'occurred_on' => '2026-10-05',
            'expense_type' => 'fuel',
            'note' => 'Generator diesel',
            'vault_id' => $this->zhako->id,
        ]);

        $this->assertSame(VaultLine::KIND_EXPENSE, $line->kind);
        $this->assertSame(75.5, (float) $line->amount);
        $this->assertSame(0.0, (float) $line->hold_amount);
        $this->assertNull($line->hold_pool);
        $this->assertNull($line->unlock_date);
        $this->assertSame('fuel', $line->expense_type);
    }

    public function test_estimate_reports_shortfall_when_available_cash_too_low(): void
    {
        Carbon::setTestNow('2026-10-15');

        Staff::query()->create([
            'name' => 'Pay Me',
            'kind' => Staff::KIND_SALARY,
            'monthly_salary' => 800,
            'currency' => DualCurrency::USD,
        ]);

        // Only 200 available from advance (90% of 222.23 ≈ 200)
        $this->vault->postAdvance([
            'amount' => 222.23,
            'currency' => DualCurrency::USD,
            'occurred_on' => '2026-10-01',
            'vault_id' => $this->zhako->id,
        ]);

        $estimate = $this->vault->estimate(
            DualCurrency::USD,
            '2026-10-15',
            salariesToPay: 800.0,
            expensesToPay: 50.0,
            vault: $this->zhako,
        );

        $this->assertFalse($estimate['covers']);
        $this->assertSame(200.01, $estimate['available_cash']); // 90% of 222.23
        $this->assertSame(800.0, $estimate['salaries_to_pay']);
        $this->assertSame(50.0, $estimate['expenses_to_pay']);
        $this->assertSame(850.0, $estimate['obligations']);
        $this->assertEqualsWithDelta(649.99, $estimate['shortfall'], 0.01);
        $this->assertSame(22.22, $estimate['company_insurance_held']);
    }

    public function test_estimate_covers_when_cash_meets_obligations(): void
    {
        $this->vault->postAdvance([
            'amount' => 2000,
            'currency' => DualCurrency::USD,
            'occurred_on' => '2026-10-01',
            'vault_id' => $this->zhako->id,
        ]);

        $estimate = $this->vault->estimate(
            DualCurrency::USD,
            '2026-10-15',
            salariesToPay: 1000.0,
            expensesToPay: 200.0,
            vault: $this->zhako,
        );

        $this->assertTrue($estimate['covers']);
        $this->assertSame(1800.0, $estimate['available_cash']);
        $this->assertSame(0.0, $estimate['shortfall']);
    }

    public function test_currencies_never_blend_on_one_line(): void
    {
        $usd = $this->vault->postAdvance([
            'amount' => 100,
            'currency' => DualCurrency::USD,
            'vault_id' => $this->zhako->id,
        ]);
        $iqd = $this->vault->postExpense([
            'amount' => 50,
            'currency' => DualCurrency::IQD,
            'vault_id' => $this->zhako->id,
        ]);

        $this->assertSame(DualCurrency::USD, $usd->currency);
        $this->assertSame(DualCurrency::IQD, $iqd->currency);
        $this->assertSame(90.0, $this->vault->balances(DualCurrency::USD, null, $this->zhako)['available_cash']);
        $this->assertSame(-50.0, $this->vault->balances(DualCurrency::IQD, null, $this->zhako)['available_cash']);
    }
}
