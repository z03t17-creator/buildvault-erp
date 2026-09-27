<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkerModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_worker_belongs_to_project_with_decimal_defaults(): void
    {
        $project = Project::query()->create([
            'name' => 'Site Alpha',
            'status' => Project::STATUS_ACTIVE,
        ]);

        $worker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Aso Karim',
            'role' => Worker::ROLE_ENGINEER,
            'daily_rate_usd' => 45.5,
            'overtime_rate_usd' => 60.25,
            'phone' => '+9647500000000',
            'national_id_number' => 'NID-1001',
        ]);

        $this->assertTrue($worker->project->is($project));
        $this->assertTrue($project->workers->contains($worker));
        $this->assertSame(Worker::ROLE_ENGINEER, $worker->role);
        $this->assertSame('45.50', (string) $worker->daily_rate_usd);
        $this->assertSame('60.25', (string) $worker->overtime_rate_usd);
        $this->assertSame('0.00', (string) $worker->spending_limit_usd);
        $this->assertTrue(method_exists($worker, 'attendances'));
        $this->assertTrue(method_exists($worker, 'payouts'));
    }

    public function test_worker_project_id_can_be_null(): void
    {
        $worker = Worker::query()->create([
            'name' => 'Unassigned Laborer',
            'role' => Worker::ROLE_LABORER,
        ]);

        $this->assertNull($worker->project_id);
        $this->assertNull($worker->project);
    }
}
