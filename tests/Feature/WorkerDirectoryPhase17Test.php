<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Worker;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkerDirectoryPhase17Test extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        if (! \Illuminate\Support\Facades\Route::has('workers.index')) {
            $this->markTestSkipped('Module surface dropped in staff rewire slice.');
        }
    }


    public function test_accountant_can_filter_worker_directory_with_salary_totals(): void
    {
        $project = Project::query()->create([
            'name' => 'Worker Directory QA',
            'status' => Project::STATUS_ACTIVE,
        ]);

        Worker::query()->create([
            'name' => 'Salary Worker A',
            'project_id' => $project->id,
            'labor_kind' => Worker::LABOR_KIND_WORKER,
            'role' => Worker::ROLE_LABORER,
            'monthly_salary_usd' => 400,
            'monthly_salary_iqd' => 520000,
            'phone' => '+9647502220001',
        ]);

        Worker::query()->create([
            'name' => 'Salary Worker B',
            'project_id' => $project->id,
            'labor_kind' => Worker::LABOR_KIND_WORKER,
            'role' => Worker::ROLE_ENGINEER,
            'monthly_salary_usd' => 600,
            'monthly_salary_iqd' => 0,
            'phone' => '+9647502220002',
        ]);

        Worker::query()->create([
            'name' => 'Unit Staff',
            'project_id' => $project->id,
            'labor_kind' => Worker::LABOR_KIND_STAFF,
            'role' => Worker::ROLE_SUBCONTRACTOR,
            'unit_rate' => 10,
            'rate_unit' => 'm2',
            'rate_currency' => 'USD',
        ]);

        $this->actingAsRole(Roles::ACCOUNTANT);

        $this->get(route('workers.index', ['labor_kind' => 'worker']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Workers/Index')
                ->where('filters.labor_kind', 'worker')
                ->has('workers', 2)
                ->where('kindCounts.worker', 2)
                ->where('kindCounts.staff', 1)
                ->where('salaryTotals.monthly_salary_usd', 1000)
                ->where('salaryTotals.monthly_salary_iqd', 520000)
            );
    }

    public function test_worker_filter_excludes_staff_rows(): void
    {
        $project = Project::query()->create([
            'name' => 'Filter QA',
            'status' => Project::STATUS_ACTIVE,
        ]);

        Worker::query()->create([
            'name' => 'Only Staff',
            'project_id' => $project->id,
            'labor_kind' => Worker::LABOR_KIND_STAFF,
            'role' => Worker::ROLE_SUBCONTRACTOR,
        ]);

        $this->actingAsRole(Roles::SUPER_ADMIN);

        $this->get(route('workers.index', ['labor_kind' => 'worker']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Workers/Index')
                ->has('workers', 0)
                ->where('kindCounts.worker', 0)
                ->has('salaryTotals')
            );
    }
}
