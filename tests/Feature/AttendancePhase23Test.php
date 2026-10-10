<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Project;
use App\Models\Staff;
use App\Support\DualCurrency;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AttendancePhase23Test extends TestCase
{
    use RefreshDatabase;

    public function test_stock_manager_sees_day_summary_and_salary_staff_only(): void
    {
        $projectA = Project::query()->create([
            'name' => 'Tower A',
            'status' => Project::STATUS_ACTIVE,
        ]);
        $projectB = Project::query()->create([
            'name' => 'Tower B',
            'status' => Project::STATUS_ACTIVE,
        ]);

        $present = Staff::query()->create([
            'name' => 'Present Worker',
            'kind' => Staff::KIND_SALARY,
            'trade' => 'laborer',
            'monthly_salary' => 500,
            'currency' => DualCurrency::USD,
        ]);
        $late = Staff::query()->create([
            'name' => 'Late Worker',
            'kind' => Staff::KIND_SALARY,
            'trade' => 'supervisor',
            'monthly_salary' => 700,
            'currency' => DualCurrency::USD,
        ]);
        $unchecked = Staff::query()->create([
            'name' => 'Unchecked Worker',
            'kind' => Staff::KIND_SALARY,
            'trade' => 'engineer',
            'monthly_salary' => 900,
            'currency' => DualCurrency::USD,
        ]);
        Staff::query()->create([
            'name' => 'Other Project Worker',
            'kind' => Staff::KIND_SALARY,
            'trade' => 'laborer',
            'monthly_salary' => 400,
            'currency' => DualCurrency::USD,
        ]);
        // Daily staff are included; unit staff stay on the unit tab.
        Staff::query()->create([
            'name' => 'Daily Included',
            'kind' => Staff::KIND_TIME,
            'pay_model' => Staff::PAY_DAILY,
            'day_rate' => 25,
            'currency' => DualCurrency::USD,
            'trade' => 'laminate',
        ]);
        Staff::query()->create([
            'name' => 'Unit Excluded',
            'kind' => Staff::KIND_UNIT,
            'pay_model' => Staff::PAY_UNIT,
            'unit_rate' => 10,
            'rate_unit' => 'm²',
            'currency' => DualCurrency::USD,
        ]);

        $date = now()->toDateString();

        Attendance::query()->create([
            'staff_id' => $present->id,
            'project_id' => $projectA->id,
            'date' => $date,
            'status' => Attendance::STATUS_PRESENT,
            'check_in' => '08:00',
            'forfeit_day' => false,
        ]);
        Attendance::query()->create([
            'staff_id' => $late->id,
            'project_id' => $projectA->id,
            'date' => $date,
            'status' => Attendance::STATUS_LATE,
            'check_in' => '08:20',
            'late_minutes' => 20,
            'forfeit_day' => false,
        ]);

        $this->actingAsRole(Roles::STOCK_MANAGER);

        $this->get(route('attendance.index', ['date' => $date]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Attendance/Matrix')
                ->has('grid', 5)
                ->where('daySummary.staff', 5)
                ->where('daySummary.recorded', 2)
                ->where('daySummary.present', 2)
                ->where('daySummary.late', 1)
                ->where('daySummary.absent', 0)
                ->where('daySummary.unit_staff', 1)
                ->where('daySummary.forfeit_days', 0)
                ->where('daySummary.unchecked', 3)
                ->where('date', $date)
            );

        $this->get(route('attendance.index', [
            'date' => $date,
            'project_id' => $projectA->id,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Attendance/Matrix')
                // Staff has no project_id yet — matrix lists all eligible staff.
                ->has('grid', 5)
                ->where('projectId', $projectA->id)
                ->where('daySummary.staff', 5)
                ->where('daySummary.recorded', 2)
                ->where('daySummary.present', 2)
                ->where('daySummary.late', 1)
                ->where('daySummary.unchecked', 3)
            );
    }

    public function test_empty_attendance_matrix_returns_zero_summary(): void
    {
        $this->actingAsRole(Roles::SUPER_ADMIN);

        $this->get(route('attendance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Attendance/Matrix')
                ->has('grid', 0)
                ->where('daySummary.staff', 0)
                ->where('daySummary.recorded', 0)
                ->where('daySummary.unchecked', 0)
            );
    }
}
