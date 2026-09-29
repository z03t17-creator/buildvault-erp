<?php

namespace Tests\Feature;

use App\Models\ApartmentUnit;
use App\Models\BuildingBlock;
use App\Models\Project;
use App\Models\Worker;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SpatialGridPhase23Test extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private BuildingBlock $block;

    private ApartmentUnit $unit;

    private Worker $person;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::query()->create([
            'name' => 'Mayorca Spatial QA',
            'status' => Project::STATUS_ACTIVE,
        ]);

        $this->block = BuildingBlock::query()->create([
            'project_id' => $this->project->id,
            'code' => 'B1',
            'name' => 'Block 1',
            'sort_order' => 1,
        ]);

        $this->person = Worker::query()->create([
            'name' => 'Hunar',
            'project_id' => $this->project->id,
            'labor_kind' => Worker::LABOR_KIND_STAFF,
            'role' => Worker::ROLE_SUBCONTRACTOR,
        ]);

        $this->unit = ApartmentUnit::query()->create([
            'project_id' => $this->project->id,
            'building_block_id' => $this->block->id,
            'unit_label' => '01',
            'floor_number' => 1,
            'category' => ApartmentUnit::CATEGORY_MDF,
            'status' => ApartmentUnit::STATUS_PENDING,
        ]);

        // Neighbor cell for matrix shape
        ApartmentUnit::query()->create([
            'project_id' => $this->project->id,
            'building_block_id' => $this->block->id,
            'unit_label' => '02',
            'floor_number' => 1,
            'category' => ApartmentUnit::CATEGORY_MDF,
            'status' => ApartmentUnit::STATUS_PENDING,
            'is_company_crew' => true,
        ]);
    }

    public function test_accountant_can_view_spatial_grid_matrix(): void
    {
        $this->actingAsRole(Roles::ACCOUNTANT);

        $this->get(route('spatial.index', [
            'project_id' => $this->project->id,
            'block_id' => $this->block->id,
            'category' => ApartmentUnit::CATEGORY_MDF,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Spatial/Index')
                ->where('filters.category', 'mdf')
                ->where('matrix.summary.total', 2)
                ->where('matrix.summary.company_crew', 1)
                ->has('matrix.floors')
                ->has('matrix.units')
                ->has('matrix.cells')
            );
    }

    public function test_stock_manager_cannot_view_or_update_spatial_grid(): void
    {
        $stock = $this->userWithRole(Roles::STOCK_MANAGER);

        $this->actingAs($stock)
            ->get(route('spatial.index'))
            ->assertForbidden();

        $this->actingAs($stock)
            ->patch(route('spatial.units.update', $this->unit), [
                'status' => ApartmentUnit::STATUS_COMPLETED,
            ])
            ->assertForbidden();
    }

    public function test_boss_can_view_but_not_update_spatial_cells(): void
    {
        $boss = $this->actingAsRole(Roles::BOSS_CONTRACTOR);

        $this->get(route('spatial.index', [
            'project_id' => $this->project->id,
            'category' => 'mdf',
        ]))->assertOk();

        $this->actingAs($boss)
            ->patch(route('spatial.units.update', $this->unit), [
                'status' => ApartmentUnit::STATUS_IN_PROGRESS,
            ])
            ->assertForbidden();
    }

    public function test_accountant_can_assign_person_and_status_on_cell(): void
    {
        $this->actingAsRole(Roles::ACCOUNTANT);

        $this->patch(route('spatial.units.update', $this->unit), [
            'status' => ApartmentUnit::STATUS_IN_PROGRESS,
            'assigned_worker_id' => $this->person->id,
            'is_company_crew' => false,
            'notes' => 'Started MDF hang',
        ])->assertRedirect();

        $this->unit->refresh();
        $this->assertSame(ApartmentUnit::STATUS_IN_PROGRESS, $this->unit->status);
        $this->assertSame($this->person->id, $this->unit->assigned_worker_id);
        $this->assertFalse($this->unit->is_company_crew);
        $this->assertSame('Started MDF hang', $this->unit->notes);
    }

    public function test_accountant_can_assign_xoman_company_crew(): void
    {
        $this->actingAsRole(Roles::ACCOUNTANT);

        $this->unit->update([
            'assigned_worker_id' => $this->person->id,
            'is_company_crew' => false,
        ]);

        $this->patch(route('spatial.units.update', $this->unit), [
            'status' => ApartmentUnit::STATUS_COMPLETED,
            'is_company_crew' => true,
            'assigned_worker_id' => $this->person->id, // ignored when crew
        ])->assertRedirect();

        $this->unit->refresh();
        $this->assertTrue($this->unit->is_company_crew);
        $this->assertNull($this->unit->assigned_worker_id);
        $this->assertSame(ApartmentUnit::STATUS_COMPLETED, $this->unit->status);
    }

    public function test_bulk_assign_updates_selected_cells(): void
    {
        $this->actingAsRole(Roles::ACCOUNTANT);

        $second = ApartmentUnit::query()
            ->where('unit_label', '02')
            ->where('category', ApartmentUnit::CATEGORY_MDF)
            ->firstOrFail();

        $this->post(route('spatial.bulk-assign'), [
            'unit_ids' => [$this->unit->id, $second->id],
            'status' => ApartmentUnit::STATUS_INSPECTED,
            'assigned_worker_id' => $this->person->id,
            'is_company_crew' => false,
        ])->assertRedirect();

        $this->assertSame(ApartmentUnit::STATUS_INSPECTED, $this->unit->fresh()->status);
        $this->assertSame($this->person->id, $this->unit->fresh()->assigned_worker_id);
        $this->assertSame(ApartmentUnit::STATUS_INSPECTED, $second->fresh()->status);
        $this->assertFalse($second->fresh()->is_company_crew);
    }

    public function test_guest_cannot_access_spatial_routes(): void
    {
        $this->get(route('spatial.index'))->assertRedirect(route('login'));
        $this->patch(route('spatial.units.update', $this->unit), [
            'status' => ApartmentUnit::STATUS_COMPLETED,
        ])->assertRedirect(route('login'));
    }

    public function test_statuses_are_pending_in_progress_completed_inspected(): void
    {
        $this->assertSame(
            ['pending', 'in_progress', 'completed', 'inspected'],
            ApartmentUnit::STATUSES,
        );
        $this->assertNotContains('company', ApartmentUnit::STATUSES);
        $this->assertNotContains('done', ApartmentUnit::STATUSES);
    }
}
