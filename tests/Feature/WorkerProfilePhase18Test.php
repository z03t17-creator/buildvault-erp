<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Penalty;
use App\Models\Project;
use App\Models\Worker;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkerProfilePhase18Test extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_can_view_worker_profile_with_salary_attendance_and_penalties(): void
    {
        $project = Project::query()->create([
            'name' => 'Worker Profile QA',
            'status' => Project::STATUS_ACTIVE,
        ]);

        $worker = Worker::query()->create([
            'name' => 'Salary Profile Worker',
            'project_id' => $project->id,
            'labor_kind' => Worker::LABOR_KIND_WORKER,
            'role' => Worker::ROLE_LABORER,
            'monthly_salary_usd' => 450,
            'monthly_salary_iqd' => 585000,
            'phone' => '+9647503330001',
        ]);

        Attendance::query()->create([
            'worker_id' => $worker->id,
            'project_id' => $project->id,
            'date' => now()->startOfMonth()->toDateString(),
            'status' => Attendance::STATUS_PRESENT,
            'forfeit_day' => false,
        ]);

        Attendance::query()->create([
            'worker_id' => $worker->id,
            'project_id' => $project->id,
            'date' => now()->startOfMonth()->addDay()->toDateString(),
            'status' => Attendance::STATUS_LATE,
            'forfeit_day' => true,
            'late_minutes' => 45,
        ]);

        Penalty::query()->create([
            'worker_id' => $worker->id,
            'project_id' => $project->id,
            'type' => Penalty::TYPE_FORFEIT_DAY,
            'reason' => 'Late > 30m',
            'amount_usd' => 15,
            'amount_iqd' => 0,
            'currency' => 'USD',
            'occurred_on' => now()->startOfMonth()->addDay()->toDateString(),
            'status' => Penalty::STATUS_PENDING,
        ]);

        $this->actingAsRole(Roles::ACCOUNTANT);

        $this->get(route('workers.show', $worker))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Workers/Show')
                ->where('worker.id', $worker->id)
                ->where('worker.labor_kind', Worker::LABOR_KIND_WORKER)
                ->where('worker.monthly_salary_usd', '450.00')
                ->where('worker.monthly_salary_iqd', '585000.00')
                ->has('penalties', 1)
                ->where('penalties.0.type', Penalty::TYPE_FORFEIT_DAY)
                ->has('attendanceMonth')
                ->where('attendanceMonth.present', 1)
                ->where('attendanceMonth.late', 1)
                ->where('attendanceMonth.forfeit_days', 1)
                ->has('advances')
                ->has('settlement')
            );
    }

    public function test_staff_profile_still_loads_without_worker_salary_blocks_breaking(): void
    {
        $project = Project::query()->create([
            'name' => 'Staff Still Works',
            'status' => Project::STATUS_ACTIVE,
        ]);

        $staff = Worker::query()->create([
            'name' => 'Amber Staff',
            'project_id' => $project->id,
            'labor_kind' => Worker::LABOR_KIND_STAFF,
            'role' => Worker::ROLE_SUBCONTRACTOR,
            'unit_rate' => 12,
            'rate_unit' => 'm2',
            'rate_currency' => 'USD',
        ]);

        $this->actingAsRole(Roles::SUPER_ADMIN);

        $this->get(route('workers.show', $staff))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Workers/Show')
                ->where('worker.labor_kind', Worker::LABOR_KIND_STAFF)
                ->has('penalties')
                ->has('attendanceMonth')
                ->has('settlement')
            );
    }
}
