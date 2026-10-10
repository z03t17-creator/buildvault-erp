<?php

namespace Tests\Feature;

use App\Models\ProductionRecord;
use App\Models\Project;
use App\Models\Worker;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Legacy productions UI redirects into the warehouse hub.
 */
class ProductionPhase9Test extends TestCase
{
    use RefreshDatabase;

    public function test_productions_routes_redirect_to_stock(): void
    {
        $accountant = $this->userWithRole(Roles::ACCOUNTANT);
        $project = Project::query()->create(['name' => 'Prod Site']);
        $worker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Prod Worker',
        ]);
        $record = ProductionRecord::query()->create([
            'worker_id' => $worker->id,
            'project_id' => $project->id,
            'unit_type' => ProductionRecord::UNIT_APARTMENT,
            'assigned' => 10,
            'completed' => 4,
            'received' => 3,
            'remaining' => 6,
            'recorded_on' => '2026-09-20',
            'entered_by' => $accountant->id,
        ]);

        $this->actingAs($accountant)
            ->get(route('productions.index'))
            ->assertRedirect('/stock');

        $this->actingAs($accountant)
            ->get(route('productions.create'))
            ->assertRedirect('/stock');

        $this->actingAs($accountant)
            ->get(route('productions.show', $record))
            ->assertRedirect(route('stock.dashboard'));

        $this->actingAs($accountant)
            ->get(route('productions.edit', $record))
            ->assertRedirect(route('stock.dashboard'));

        $this->actingAs($accountant)
            ->post(route('productions.store'), [
                'worker_id' => $worker->id,
                'project_id' => $project->id,
                'unit_type' => ProductionRecord::UNIT_APARTMENT,
                'assigned' => 12,
                'completed' => 5,
                'received' => 4,
                'recorded_on' => '2026-09-20',
            ])
            ->assertRedirect(route('stock.dashboard'));

        $this->actingAs($accountant)
            ->put(route('productions.update', $record), [
                'worker_id' => $worker->id,
                'project_id' => $project->id,
                'unit_type' => ProductionRecord::UNIT_APARTMENT,
                'assigned' => 12,
                'completed' => 6,
                'received' => 5,
                'recorded_on' => '2026-09-21',
            ])
            ->assertRedirect(route('stock.dashboard'));

        $this->assertSame(1, ProductionRecord::query()->count());
    }

    public function test_stock_manager_productions_also_redirect(): void
    {
        $stock = $this->userWithRole(Roles::STOCK_MANAGER);

        $this->actingAs($stock)
            ->get(route('productions.index'))
            ->assertRedirect('/stock');

        $this->actingAs($stock)
            ->get(route('productions.create'))
            ->assertRedirect('/stock');
    }
}
