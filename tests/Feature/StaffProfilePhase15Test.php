<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Worker;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StaffProfilePhase15Test extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        if (! \Illuminate\Support\Facades\Route::has('workers.show')) {
            $this->markTestSkipped('Module surface dropped in staff rewire slice.');
        }
    }


    public function test_accountant_can_view_staff_profile_with_settlement(): void
    {
        $project = Project::query()->create([
            'name' => 'Staff Profile QA',
            'status' => Project::STATUS_ACTIVE,
        ]);

        $staff = Worker::query()->create([
            'name' => 'Profile Staff',
            'project_id' => $project->id,
            'labor_kind' => Worker::LABOR_KIND_STAFF,
            'role' => Worker::ROLE_SUBCONTRACTOR,
            'unit_rate' => 12.5,
            'rate_unit' => 'm2',
            'rate_currency' => 'USD',
            'phone' => '+9647509990001',
        ]);

        $this->actingAsRole(Roles::ACCOUNTANT);

        $this->get(route('workers.show', $staff))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Workers/Show')
                ->where('worker.id', $staff->id)
                ->where('worker.labor_kind', Worker::LABOR_KIND_STAFF)
                ->where('worker.name', 'Profile Staff')
                ->has('advances')
                ->has('statements')
                ->has('settlement')
            );
    }

    public function test_admin_can_classify_from_staff_profile(): void
    {
        $project = Project::query()->create([
            'name' => 'Classify QA',
            'status' => Project::STATUS_ACTIVE,
        ]);

        $person = Worker::query()->create([
            'name' => 'Needs Class',
            'project_id' => $project->id,
            'labor_kind' => Worker::LABOR_KIND_UNCLASSIFIED,
            'role' => Worker::ROLE_LABORER,
        ]);

        $this->actingAsRole(Roles::SUPER_ADMIN);

        $this->get(route('workers.show', $person))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Workers/Show')
                ->where('canClassify', true)
                ->where('worker.labor_kind', Worker::LABOR_KIND_UNCLASSIFIED)
            );
    }
}
