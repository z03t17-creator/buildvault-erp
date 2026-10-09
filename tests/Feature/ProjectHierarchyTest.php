<?php

namespace Tests\Feature;

use App\Models\Floor;
use App\Models\Project;
use App\Models\Tower;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_tower_floor_relationships_and_defaults(): void
    {
        $project = Project::query()->create([
            'name' => 'Zhako Residences',
            'status' => Project::STATUS_ACTIVE,
        ]);

        $tower = $project->towers()->create(['name' => 'Tower A']);
        $floor = $tower->floors()->create(['name' => 'Floor 1']);

        $this->assertTrue($tower->project->is($project));
        $this->assertTrue($floor->tower->is($tower));
        $this->assertTrue($project->floors->contains($floor));

        $this->assertSame('45.00', (string) $project->allocation_expenses_pct);
        $this->assertSame('30.00', (string) $project->allocation_payroll_pct);
        $this->assertSame('10.00', (string) $project->allocation_insurance_pct);
        $this->assertSame('5.00', (string) $project->allocation_penalty_pct);
        $this->assertSame('10.00', (string) $project->allocation_profit_pct);
        $this->assertSame(Project::STATUS_ACTIVE, $project->status);

        $this->assertDatabaseHas('towers', [
            'project_id' => $project->id,
            'name' => 'Tower A',
        ]);
        $this->assertDatabaseHas('floors', [
            'tower_id' => $tower->id,
            'name' => 'Floor 1',
        ]);
    }
}
