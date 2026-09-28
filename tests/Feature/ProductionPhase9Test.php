<?php

namespace Tests\Feature;

use App\Models\ProductionRecord;
use App\Models\Project;
use App\Models\Worker;
use App\Support\Roles;
use Database\Seeders\DemoHierarchySeeder;
use Database\Seeders\DemoProductionSeeder;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductionPhase9Test extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_can_create_production_with_correct_math(): void
    {
        $accountant = $this->userWithRole(Roles::ACCOUNTANT);
        $project = Project::query()->create(['name' => 'Prod Site']);
        $worker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Prod Worker',
        ]);

        $this->actingAs($accountant)
            ->post(route('productions.store'), [
                'worker_id' => $worker->id,
                'project_id' => $project->id,
                'unit_type' => ProductionRecord::UNIT_APARTMENT,
                'assigned' => 12,
                'completed' => 5,
                'received' => 4,
                'recorded_on' => '2026-09-20',
                'notes' => 'Phase 9 create',
            ])
            ->assertRedirect();

        $record = ProductionRecord::query()->first();
        $this->assertNotNull($record);
        $this->assertSame('12.00', (string) $record->assigned);
        $this->assertSame('5.00', (string) $record->completed);
        $this->assertSame('4.00', (string) $record->received);
        $this->assertSame('7.00', (string) $record->remaining);
        $this->assertSame(41.67, $record->progress_pct);
        $this->assertSame($accountant->id, $record->entered_by);
    }

    public function test_accountant_can_edit_production(): void
    {
        $accountant = $this->userWithRole(Roles::ACCOUNTANT);
        $project = Project::query()->create(['name' => 'Edit Site']);
        $worker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Edit Worker',
        ]);

        $record = ProductionRecord::query()->create([
            'worker_id' => $worker->id,
            'project_id' => $project->id,
            'unit_type' => ProductionRecord::UNIT_FLOOR,
            'assigned' => 10,
            'completed' => 2,
            'received' => 1,
            'remaining' => 8,
            'recorded_on' => '2026-09-10',
            'entered_by' => $accountant->id,
        ]);

        $this->actingAs($accountant)
            ->put(route('productions.update', $record), [
                'worker_id' => $worker->id,
                'project_id' => $project->id,
                'unit_type' => ProductionRecord::UNIT_VILLA,
                'assigned' => 10,
                'completed' => 4,
                'received' => 3,
                'recorded_on' => '2026-09-11',
                'notes' => 'Updated',
            ])
            ->assertRedirect(route('productions.show', $record));

        $record->refresh();
        $this->assertSame(ProductionRecord::UNIT_VILLA, $record->unit_type);
        $this->assertSame('6.00', (string) $record->remaining);
        $this->assertSame(40.0, $record->progress_pct);
        $this->assertSame('3.00', (string) $record->received);
    }

    public function test_custom_unit_type_requires_label(): void
    {
        $accountant = $this->userWithRole(Roles::ACCOUNTANT);
        $project = Project::query()->create(['name' => 'Custom Site']);
        $worker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Custom Worker',
        ]);

        $this->actingAs($accountant)
            ->post(route('productions.store'), [
                'worker_id' => $worker->id,
                'project_id' => $project->id,
                'unit_type' => ProductionRecord::UNIT_OTHER,
                'unit_label' => '',
                'assigned' => 5,
                'completed' => 1,
                'recorded_on' => '2026-09-20',
            ])
            ->assertSessionHasErrors('unit_label');

        $this->actingAs($accountant)
            ->post(route('productions.store'), [
                'worker_id' => $worker->id,
                'project_id' => $project->id,
                'unit_type' => ProductionRecord::UNIT_OTHER,
                'unit_label' => 'Parking bay',
                'assigned' => 5,
                'completed' => 1,
                'received' => 0,
                'recorded_on' => '2026-09-20',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('production_records', [
            'unit_type' => ProductionRecord::UNIT_OTHER,
            'unit_label' => 'Parking bay',
        ]);
    }

    public function test_index_filters_by_project_and_worker(): void
    {
        $admin = $this->userWithRole(Roles::SUPER_ADMIN);
        $projectA = Project::query()->create(['name' => 'Filter A']);
        $projectB = Project::query()->create(['name' => 'Filter B']);
        $workerA = Worker::query()->create(['project_id' => $projectA->id, 'name' => 'Worker A']);
        $workerB = Worker::query()->create(['project_id' => $projectB->id, 'name' => 'Worker B']);

        ProductionRecord::query()->create([
            'worker_id' => $workerA->id,
            'project_id' => $projectA->id,
            'unit_type' => ProductionRecord::UNIT_ROOM,
            'assigned' => 4,
            'completed' => 1,
            'received' => 0,
            'remaining' => 3,
            'recorded_on' => '2026-09-01',
        ]);
        ProductionRecord::query()->create([
            'worker_id' => $workerB->id,
            'project_id' => $projectB->id,
            'unit_type' => ProductionRecord::UNIT_ROOM,
            'assigned' => 2,
            'completed' => 2,
            'received' => 2,
            'remaining' => 0,
            'recorded_on' => '2026-09-02',
        ]);

        $this->actingAs($admin)
            ->get(route('productions.index', ['project_id' => $projectA->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Productions/Index')
                ->has('productions', 1)
                ->where('productions.0.worker.name', 'Worker A')
            );

        $this->actingAs($admin)
            ->get(route('productions.index', ['worker_id' => $workerB->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('productions', 1)
                ->where('productions.0.worker.name', 'Worker B')
            );
    }

    public function test_boss_can_view_but_not_create_or_edit(): void
    {
        $boss = $this->userWithRole(Roles::BOSS_CONTRACTOR);
        $project = Project::query()->create(['name' => 'Boss Prod']);
        $worker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Boss Prod Worker',
        ]);
        $record = ProductionRecord::query()->create([
            'worker_id' => $worker->id,
            'project_id' => $project->id,
            'unit_type' => ProductionRecord::UNIT_BUILDING,
            'assigned' => 1,
            'completed' => 0,
            'received' => 0,
            'remaining' => 1,
            'recorded_on' => '2026-09-01',
        ]);

        $this->actingAs($boss)->get(route('productions.index'))->assertOk();
        $this->actingAs($boss)->get(route('productions.show', $record))->assertOk();
        $this->actingAs($boss)->get(route('productions.create'))->assertForbidden();
        $this->actingAs($boss)->get(route('productions.edit', $record))->assertForbidden();
        $this->actingAs($boss)
            ->post(route('productions.store'), [
                'worker_id' => $worker->id,
                'project_id' => $project->id,
                'unit_type' => ProductionRecord::UNIT_ROOM,
                'assigned' => 1,
                'completed' => 0,
                'recorded_on' => '2026-09-01',
            ])
            ->assertForbidden();
    }

    public function test_stock_manager_cannot_access_productions(): void
    {
        $stock = $this->userWithRole(Roles::STOCK_MANAGER);

        $this->actingAs($stock)->get(route('productions.index'))->assertForbidden();
        $this->actingAs($stock)->get(route('productions.create'))->assertForbidden();
    }

    public function test_demo_production_seeder_creates_zhako_demo_rows(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(DemoUsersSeeder::class);
        $this->seed(DemoHierarchySeeder::class);
        $this->seed(DemoProductionSeeder::class);

        $project = Project::query()->where('name', DemoHierarchySeeder::PROJECT_NAME)->first();
        $this->assertNotNull($project);

        $rows = ProductionRecord::query()->where('project_id', $project->id)->get();
        $this->assertGreaterThanOrEqual(3, $rows->count());

        $partial = $rows->firstWhere(fn ($r) => str_contains((string) $r->notes, DemoProductionSeeder::MARKERS[0]));
        $this->assertNotNull($partial);
        $this->assertSame('7.00', (string) $partial->remaining);
        $this->assertSame(41.67, $partial->progress_pct);

        // Idempotent
        $this->seed(DemoProductionSeeder::class);
        $this->assertSame($rows->count(), ProductionRecord::query()->where('project_id', $project->id)->count());
    }
}
