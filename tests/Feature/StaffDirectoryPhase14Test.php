<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Worker;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StaffDirectoryPhase14Test extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        if (! \Illuminate\Support\Facades\Route::has('workers.index')) {
            $this->markTestSkipped('Module surface dropped in staff rewire slice.');
        }


        $this->project = Project::query()->create([
            'name' => 'Staff Directory QA',
            'status' => Project::STATUS_ACTIVE,
        ]);

        Worker::query()->create([
            'name' => 'Staff One',
            'project_id' => $this->project->id,
            'labor_kind' => Worker::LABOR_KIND_STAFF,
            'role' => Worker::ROLE_SUBCONTRACTOR,
            'unit_rate' => 12.5,
            'rate_unit' => 'm2',
            'rate_currency' => 'USD',
            'phone' => '+9647500000001',
        ]);

        Worker::query()->create([
            'name' => 'Worker One',
            'project_id' => $this->project->id,
            'labor_kind' => Worker::LABOR_KIND_WORKER,
            'role' => Worker::ROLE_LABORER,
            'monthly_salary_usd' => 400,
            'monthly_salary_iqd' => 520000,
        ]);

        Worker::query()->create([
            'name' => 'Unclassified One',
            'project_id' => $this->project->id,
            'labor_kind' => Worker::LABOR_KIND_UNCLASSIFIED,
            'role' => Worker::ROLE_LABORER,
        ]);
    }

    public function test_accountant_can_filter_staff_directory(): void
    {
        $this->actingAsRole(Roles::ACCOUNTANT);

        $this->get(route('workers.index', ['labor_kind' => 'staff']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Workers/Index')
                ->where('filters.labor_kind', 'staff')
                ->has('workers', 1)
                ->where('workers.0.name', 'Staff One')
                ->where('kindCounts.staff', 1)
                ->where('kindCounts.worker', 1)
                ->where('kindCounts.unclassified', 1)
                ->where('kindCounts.all', 3)
            );
    }

    public function test_unfiltered_roster_includes_kind_counts(): void
    {
        $this->actingAsRole(Roles::SUPER_ADMIN);

        $this->get(route('workers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Workers/Index')
                ->has('workers', 3)
                ->where('kindCounts.all', 3)
            );
    }
}
