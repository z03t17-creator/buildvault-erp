<?php

namespace Database\Seeders;

use App\Models\Floor;
use App\Models\Project;
use App\Models\Tower;
use App\Models\Worker;
use Illuminate\Database\Seeder;

/**
 * Demo project tree + sample workers (idempotent by project name).
 * No attendance seed — payroll OT uses manual_ot_hours only.
 */
class DemoHierarchySeeder extends Seeder
{
    public const PROJECT_NAME = 'Zhako Demo Tower';

    public const PROJECT_B_NAME = 'Zhako Site B (Planning)';

    public function run(): void
    {
        $project = Project::query()->updateOrCreate(
            ['name' => self::PROJECT_NAME],
            [
                'description' => 'Primary active demo site for payroll, vault, stock, and settlements QA',
                'client' => 'Zhako Holdings',
                'location' => 'Erbil',
                'contract_number' => 'ZH-2026-001',
                'status' => Project::STATUS_ACTIVE,
                'total_budget_usd' => 250000,
                'contract_value_iqd' => 500_000_000,
                'budget_iqd' => 327_500_000,
                'start_date' => now()->subMonths(2)->toDateString(),
                'end_date' => now()->addMonths(10)->toDateString(),
            ],
        );

        $tower = Tower::query()->firstOrCreate(
            ['project_id' => $project->id, 'name' => 'Tower A'],
        );

        Floor::query()->firstOrCreate(
            ['tower_id' => $tower->id, 'name' => 'Ground Floor'],
        );
        Floor::query()->firstOrCreate(
            ['tower_id' => $tower->id, 'name' => 'Floor 1'],
        );
        Floor::query()->firstOrCreate(
            ['tower_id' => $tower->id, 'name' => 'Floor 2'],
        );

        $workers = [
            [
                'name' => 'Demo Engineer',
                'role' => Worker::ROLE_ENGINEER,
                'daily_rate_usd' => 80,
                'overtime_rate_usd' => 120,
                'manual_ot_hours' => 4,
                'national_id_number' => 'DEMO-ENG-001',
            ],
            [
                'name' => 'Demo Supervisor',
                'role' => Worker::ROLE_SUPERVISOR,
                'daily_rate_usd' => 55,
                'overtime_rate_usd' => 80,
                'manual_ot_hours' => 2,
                'national_id_number' => 'DEMO-SUP-001',
            ],
            [
                'name' => 'Demo Laborer One',
                'role' => Worker::ROLE_LABORER,
                'daily_rate_usd' => 25,
                'overtime_rate_usd' => 35,
                'manual_ot_hours' => 6,
                'national_id_number' => 'DEMO-LAB-001',
            ],
            [
                'name' => 'Demo Laborer Two',
                'role' => Worker::ROLE_LABORER,
                'daily_rate_usd' => 25,
                'overtime_rate_usd' => 35,
                'manual_ot_hours' => 0,
                'national_id_number' => 'DEMO-LAB-002',
            ],
            [
                'name' => 'Demo Laborer Three',
                'role' => Worker::ROLE_LABORER,
                'daily_rate_usd' => 28,
                'overtime_rate_usd' => 40,
                'manual_ot_hours' => 1.5,
                'national_id_number' => 'DEMO-LAB-003',
            ],
            [
                'name' => 'Demo Subcontractor',
                'role' => Worker::ROLE_SUBCONTRACTOR,
                'daily_rate_usd' => 70,
                'overtime_rate_usd' => 95,
                'manual_ot_hours' => 0,
                'national_id_number' => 'DEMO-SUB-001',
            ],
            [
                'name' => 'Demo Site Guard',
                'role' => Worker::ROLE_LABORER,
                'daily_rate_usd' => 22,
                'overtime_rate_usd' => 30,
                'manual_ot_hours' => 8,
                'national_id_number' => 'DEMO-LAB-004',
            ],
        ];

        foreach ($workers as $data) {
            Worker::query()->updateOrCreate(
                ['national_id_number' => $data['national_id_number']],
                array_merge($data, [
                    'project_id' => $project->id,
                    'phone' => '+9647500000000',
                ]),
            );
        }

        $this->seedSecondaryProject();
    }

    /**
     * Second project for multi-project filters (stock attribution, settlements scope).
     */
    private function seedSecondaryProject(): void
    {
        $projectB = Project::query()->updateOrCreate(
            ['name' => self::PROJECT_B_NAME],
            [
                'description' => 'Secondary planning-stage site for multi-project QA',
                'client' => 'Zhako Holdings',
                'location' => 'Sulaymaniyah',
                'contract_number' => 'ZH-2026-002',
                'status' => Project::STATUS_PLANNING,
                'total_budget_usd' => 80000,
                'contract_value_iqd' => 120_000_000,
                'budget_iqd' => 45_000_000,
                'start_date' => now()->addMonth()->toDateString(),
                'end_date' => now()->addMonths(14)->toDateString(),
            ],
        );

        $towerB = Tower::query()->firstOrCreate(
            ['project_id' => $projectB->id, 'name' => 'Block 1'],
        );

        Floor::query()->firstOrCreate(
            ['tower_id' => $towerB->id, 'name' => 'Basement'],
        );

        Worker::query()->updateOrCreate(
            ['national_id_number' => 'DEMO-B-ENG-001'],
            [
                'name' => 'Site B Engineer',
                'role' => Worker::ROLE_ENGINEER,
                'daily_rate_usd' => 75,
                'overtime_rate_usd' => 110,
                'manual_ot_hours' => 0,
                'project_id' => $projectB->id,
                'phone' => '+9647500000001',
            ],
        );
    }
}
