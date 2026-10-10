<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Penalty;
use App\Models\Project;
use App\Models\Staff;
use App\Models\Vault;
use App\Services\SimpleVaultService;
use App\Support\DualCurrency;
use App\Support\Roles;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AttendanceDeskTest extends TestCase
{
    use RefreshDatabase;

    public function test_desk_lists_monthly_and_daily_staff_excludes_unit(): void
    {
        Staff::query()->create([
            'name' => 'Monthly One',
            'pay_model' => Staff::PAY_MONTHLY,
            'monthly_salary' => 600,
            'currency' => DualCurrency::USD,
        ]);
        Staff::query()->create([
            'name' => 'Daily One',
            'pay_model' => Staff::PAY_DAILY,
            'day_rate' => 40,
            'currency' => DualCurrency::USD,
        ]);
        Staff::query()->create([
            'name' => 'Unit Skip',
            'pay_model' => Staff::PAY_UNIT,
            'unit_rate' => 12,
            'rate_unit' => 'm²',
            'currency' => DualCurrency::USD,
        ]);

        $this->actingAsRole(Roles::STOCK_MANAGER);

        $this->get(route('attendance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Attendance/Matrix')
                ->has('grid', 2)
                ->where('daySummary.staff', 2)
                ->where('daySummary.unit_staff', 1)
                ->has('unitStaff', 1)
            );
    }

    public function test_mark_all_present_and_status_toggles_autosave(): void
    {
        Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 0,
            'balance_iqd' => 0,
        ]);
        Project::query()->create([
            'name' => 'Site',
            'status' => Project::STATUS_ACTIVE,
        ]);

        $monthly = Staff::query()->create([
            'name' => 'Salaried',
            'pay_model' => Staff::PAY_MONTHLY,
            'monthly_salary' => 300,
            'currency' => DualCurrency::USD,
        ]);
        $daily = Staff::query()->create([
            'name' => 'Day Rate',
            'pay_model' => Staff::PAY_DAILY,
            'day_rate' => 50,
            'currency' => DualCurrency::USD,
        ]);

        $date = now()->toDateString();
        $this->actingAsRole(Roles::STOCK_MANAGER);

        $this->post(route('attendance.mark-all-present'), ['date' => $date])
            ->assertRedirect();

        $this->assertDatabaseHas('attendances', [
            'staff_id' => $monthly->id,
            'status' => Attendance::STATUS_PRESENT,
        ]);
        $this->assertDatabaseHas('attendances', [
            'staff_id' => $daily->id,
            'status' => Attendance::STATUS_PRESENT,
        ]);

        $this->post(route('attendance.status'), [
            'date' => $date,
            'staff_id' => $monthly->id,
            'status' => Attendance::STATUS_ABSENT_UNEXCUSED,
        ])->assertRedirect();

        $row = Attendance::query()->where('staff_id', $monthly->id)->whereDate('date', $date)->first();
        $this->assertNotNull($row);
        $this->assertSame(Attendance::STATUS_ABSENT_UNEXCUSED, $row->status);
        $this->assertTrue((bool) $row->forfeit_day);
        $this->assertNotNull($row->penalty_id);
        $this->assertDatabaseHas('penalties', [
            'id' => $row->penalty_id,
            'type' => Penalty::TYPE_ABSENCE,
            'staff_id' => $monthly->id,
        ]);

        $this->post(route('attendance.status'), [
            'date' => $date,
            'staff_id' => $daily->id,
            'status' => Attendance::STATUS_HALF_DAY,
        ])->assertRedirect();

        $this->get(route('attendance.index', ['date' => $date]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('daySummary.absent', 1)
                ->where('daySummary.half_day', 1)
                ->where('daySummary.wage_usd', 25)
            );
    }

    public function test_absent_cuts_monthly_salary_due_and_present_feeds_daily_due(): void
    {
        Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 0,
            'balance_iqd' => 0,
        ]);
        Project::query()->create([
            'name' => 'Site',
            'status' => Project::STATUS_ACTIVE,
        ]);

        $monthly = Staff::query()->create([
            'name' => 'Salaried',
            'pay_model' => Staff::PAY_MONTHLY,
            'monthly_salary' => 300,
            'currency' => DualCurrency::USD,
        ]);
        $daily = Staff::query()->create([
            'name' => 'Day Rate',
            'pay_model' => Staff::PAY_DAILY,
            'day_rate' => 40,
            'currency' => DualCurrency::USD,
        ]);

        $date = now()->toDateString();
        $this->actingAsRole(Roles::STOCK_MANAGER);

        $this->post(route('attendance.status'), [
            'date' => $date,
            'staff_id' => $monthly->id,
            'status' => Attendance::STATUS_ABSENT_UNEXCUSED,
        ])->assertRedirect();

        $this->post(route('attendance.status'), [
            'date' => $date,
            'staff_id' => $daily->id,
            'status' => Attendance::STATUS_PRESENT,
        ])->assertRedirect();

        $vault = app(SimpleVaultService::class);
        $dueMonthly = $vault->salaryDueFor($monthly, now());
        // 300 monthly / 30 = 10 per day → one absence → 290
        $this->assertEqualsWithDelta(290.0, $dueMonthly, 0.01);

        $dueDaily = $vault->dailyAttendanceDueFor($daily, now());
        $this->assertEqualsWithDelta(40.0, $dueDaily, 0.01);

        $open = $vault->openSalariesDue(DualCurrency::USD, now());
        $this->assertEqualsWithDelta(330.0, $open, 0.01); // 290 + 40
    }
}
