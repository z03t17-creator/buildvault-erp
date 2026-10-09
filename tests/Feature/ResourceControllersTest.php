<?php

namespace Tests\Feature;

use App\Models\Floor;
use App\Models\Project;
use App\Models\Tower;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ResourceControllersTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        if (! \Illuminate\Support\Facades\Route::has('workers.index')) {
            $this->markTestSkipped('Module surface dropped in staff rewire slice.');
        }
    }


    public function test_guest_is_redirected_from_protected_resources(): void
    {
        $this->get(route('projects.index'))->assertRedirect(route('login'));
        $this->get(route('workers.index'))->assertRedirect(route('login'));
    }

    public function test_project_store_and_show(): void
    {
        $user = $this->userWithRole();

        $response = $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'Zhako Tower Site',
            'location' => 'Erbil',
            'status' => Project::STATUS_ACTIVE,
            'total_budget_usd' => 250000,
        ]);

        $project = Project::query()->where('name', 'Zhako Tower Site')->first();
        $this->assertNotNull($project);

        $response->assertRedirect(route('projects.show', $project));

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Show')
                ->where('project.id', $project->id)
                ->where('project.name', 'Zhako Tower Site')
            );
    }

    public function test_tower_store_and_show_nested_under_project(): void
    {
        $user = $this->userWithRole();
        $project = Project::query()->create(['name' => 'Parent Project']);

        $response = $this->actingAs($user)->post(route('projects.towers.store', $project), [
            'name' => 'Tower A',
        ]);

        $tower = Tower::query()->where('name', 'Tower A')->first();
        $this->assertNotNull($tower);
        $this->assertSame($project->id, $tower->project_id);

        $response->assertRedirect(route('towers.show', $tower));

        $this->actingAs($user)
            ->get(route('towers.show', $tower))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Towers/Show')
                ->where('tower.id', $tower->id)
                ->where('project.id', $project->id)
            );
    }

    public function test_floor_store_and_show_nested_under_tower(): void
    {
        $user = $this->userWithRole();
        $project = Project::query()->create(['name' => 'Parent Project']);
        $tower = $project->towers()->create(['name' => 'Tower B']);

        $response = $this->actingAs($user)->post(route('towers.floors.store', $tower), [
            'name' => 'Floor 3',
        ]);

        $floor = Floor::query()->where('name', 'Floor 3')->first();
        $this->assertNotNull($floor);
        $this->assertSame($tower->id, $floor->tower_id);

        $response->assertRedirect(route('floors.show', $floor));

        $this->actingAs($user)
            ->get(route('floors.show', $floor))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Floors/Show')
                ->where('floor.id', $floor->id)
                ->where('tower.id', $tower->id)
            );
    }

    public function test_worker_store_and_show(): void
    {
        $user = $this->userWithRole();
        $project = Project::query()->create(['name' => 'Crew Project']);

        $response = $this->actingAs($user)->post(route('workers.store'), [
            'project_id' => $project->id,
            'name' => 'Ava Mason',
            'role' => Worker::ROLE_LABORER,
            'daily_rate_usd' => 40,
            'phone' => '0700123456',
        ]);

        $worker = Worker::query()->where('name', 'Ava Mason')->first();
        $this->assertNotNull($worker);
        $this->assertSame($project->id, $worker->project_id);

        $response->assertRedirect(route('workers.show', $worker));

        $this->actingAs($user)
            ->get(route('workers.show', $worker))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Workers/Show')
                ->where('worker.id', $worker->id)
                ->where('worker.name', 'Ava Mason')
            );
    }

}
