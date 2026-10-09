<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectStoreFinancialDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_store_accepts_iqd_only_contract_without_null_usd_crash(): void
    {
        $this->seed([
            \Database\Seeders\RoleSeeder::class,
            \Database\Seeders\UserSeeder::class,
            \Database\Seeders\VaultSeeder::class,
        ]);

        $this->actingAsRole(Roles::SUPER_ADMIN);

        $response = $this->post(route('projects.store'), [
            'name' => 'IQD Only Tower',
            'contract_value_iqd' => 2_500_000,
            'start_date' => '2025-10-05',
            'end_date' => '2026-10-31',
            'status' => 'planning',
        ]);

        $project = Project::query()->where('name', 'IQD Only Tower')->first();
        $this->assertNotNull($project, 'Project should persist without explicit USD budget');
        $response->assertRedirect(route('projects.show', $project));
        $this->assertSame('0.00', (string) $project->total_budget_usd);
        $this->assertSame('2500000.00', (string) $project->contract_value_iqd);
    }
}
