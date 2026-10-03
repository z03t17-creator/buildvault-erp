<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Project;
use App\Models\Worker;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AttendancePhase23Test extends TestCase
{
    use RefreshDatabase;

    public function test_stock_manager_sees_day_summary_and_workers_only(): void
    {
        $projectA = Project::query()->create([
            'name' => 'Tower A',
            'status' => Project::STATUS_ACTIVE,
        ]);
        $projectB = Project::query()->create([
            'name' => 'Tower B',
            'status' => Project::STATUS_ACTIVE,
        ]);

        $present = Worker::query()->create([
            'name' => 'Present Worker',
            'project_id' => $projectA->id,
            'labor_kind' => Worker::LABOR_KIND_WORKER,
            'role' => Worker::ROLE_LABORER,
            'monthly_salary_usd' => 500,
        ]);
        $late = Worker::query()->create([
            'name' => 'Late Worker',
            'project_id' => $projectA->id,
            'labor_kind' => Worker::LABOR_KIND_WORKER,
            'role' => Worker::ROLE_SUPERVISOR,
            'monthly_salary_usd' => 700,
        ]);
        $unchecked = Worker::query()->create([
            'name' => 'Unchecked Worker',
            'project_id' => $projectA->id,
            'labor_kind' => Worker::LABOR_KIND_WORKER,
            'role' => Worker::ROLE_ENGINEER,
            'monthly_salary_usd' => 900,
        ]);
        Worker::query()->create([
            'name' => 'Other Project Worker',
            'project_id' => $projectB->id,
            'labor_kind' => Worker::LABOR_KIND_WORKER,
            'role' => Worker::ROLE_LABORER,
            'monthly_salary_usd' => 400,
        ]);
        Worker::query()->create([
            'name' => 'Staff Skip',
            'project_id' => $projectA->id,
            'labor_kind' => Worker::LABOR_KIND_STAFF,
            'role' => Worker::ROLE_SUBCONTRACTOR,
            'rate_unit' => 'item',
            'rate_currency' => 'USD',
            'unit_rate' => 12,
        ]);

        $date = now()->toDateString();

        Attendance::query()->create([
            'worker_id' => $present->id,
            'project_id' => $projectA->id,
            'date' => $date,
            'status' => Attendance::STATUS_PRESENT,
            'check_in' => '08:00',
            'forfeit_day' => false,
        ]);
        Attendance::query()->create([
            'worker_id' => $late->id,
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
                ->has('grid', 4)
                ->where('daySummary.workers', 4)
                ->where('daySummary.recorded', 2)
                ->where('daySummary.present', 1)
                ->where('daySummary.late', 1)
                ->where('daySummary.absent', 0)
                ->where('daySummary.leave', 0)
                ->where('daySummary.forfeit_days', 0)
                ->where('daySummary.unchecked', 2)
                ->where('date', $date)
            );

        $this->get(route('attendance.index', [
            'date' => $date,
            'project_id' => $projectA->id,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Attendance/Matrix')
                ->has('grid', 3)
                ->where('projectId', $projectA->id)
                ->where('daySummary.workers', 3)
                ->where('daySummary.recorded', 2)
                ->where('daySummary.present', 1)
                ->where('daySummary.late', 1)
                ->where('daySummary.unchecked', 1)
                ->where('grid.0.worker.name', 'Late Worker')
                ->where('grid.1.worker.name', 'Present Worker')
                ->where('grid.2.worker.name', 'Unchecked Worker')
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
                ->where('daySummary.workers', 0)
                ->where('daySummary.recorded', 0)
                ->where('daySummary.unchecked', 0)
            );
    }
}
