<?php

namespace Database\Seeders;

use App\Models\ApartmentUnit;
use App\Models\BuildingBlock;
use App\Models\Project;
use App\Models\Worker;
use App\Services\SpatialGridService;
use Illuminate\Database\Seeder;

/**
 * Mayorca-style Floor × apartment grids (B1 / B3) for MDF, Laminate, Metxal.
 * Idempotent; gated by SEED_DEMO via DemoSeeder.
 */
class DemoSpatialSeeder extends Seeder
{
    public const PROJECT_NAME = 'Mayorca Zhako';

    public const MARKER_NOTES = 'DEMO-SPATIAL-MAYORCA';

    public function run(): void
    {
        $project = Project::query()->updateOrCreate(
            ['name' => self::PROJECT_NAME],
            [
                'description' => 'Mayorca doors/floors spatial demo (Phase 3 grid)',
                'client' => 'Zhako Construction',
                'location' => 'Erbil',
                'contract_number' => 'ZH-MAYORCA-001',
                'status' => Project::STATUS_ACTIVE,
                'total_budget_usd' => 180000,
                'contract_value_iqd' => 350_000_000,
                'budget_iqd' => 280_000_000,
                'start_date' => now()->subMonths(4)->toDateString(),
                'end_date' => now()->addMonths(8)->toDateString(),
            ],
        );

        $grid = app(SpatialGridService::class);

        $b1 = BuildingBlock::query()->updateOrCreate(
            ['project_id' => $project->id, 'code' => 'B1'],
            ['name' => 'Block 1', 'sort_order' => 1],
        );
        $b3 = BuildingBlock::query()->updateOrCreate(
            ['project_id' => $project->id, 'code' => 'B3'],
            ['name' => 'Block 3', 'sort_order' => 3],
        );

        $floors = [1, 2, 3, 4, 5];
        $unitLabels = ['01', '02', '03', '04', '05', '06'];
        $categories = [
            ApartmentUnit::CATEGORY_MDF,
            ApartmentUnit::CATEGORY_LAMINATE,
            ApartmentUnit::CATEGORY_METXAL,
        ];

        foreach ([$b1, $b3] as $block) {
            $grid->ensureGrid($project, $block, $floors, $unitLabels, $categories);
        }

        // Optional Packet / Entrance sample strip on B1 floor 1 only
        $grid->ensureGrid(
            $project,
            $b1,
            [1],
            ['01', '02', '03'],
            [ApartmentUnit::CATEGORY_PACKET, ApartmentUnit::CATEGORY_ENTRANCE],
        );

        $staff = [
            Worker::query()->updateOrCreate(
                ['national_id_number' => 'DEMO-MAY-HUNAR'],
                [
                    'name' => 'Hunar',
                    'project_id' => $project->id,
                    'role' => Worker::ROLE_SUBCONTRACTOR,
                    'labor_kind' => Worker::LABOR_KIND_STAFF,
                    'rate_unit' => 'item',
                    'rate_currency' => 'USD',
                    'unit_rate' => 15,
                    'daily_rate_usd' => 0,
                    'phone' => '+9647501110001',
                ],
            ),
            Worker::query()->updateOrCreate(
                ['national_id_number' => 'DEMO-MAY-ADIL'],
                [
                    'name' => 'Adil Laminate',
                    'project_id' => $project->id,
                    'role' => Worker::ROLE_SUBCONTRACTOR,
                    'labor_kind' => Worker::LABOR_KIND_STAFF,
                    'rate_unit' => 'm2',
                    'rate_currency' => 'USD',
                    'unit_rate' => 8.5,
                    'daily_rate_usd' => 0,
                    'phone' => '+9647501110002',
                ],
            ),
            Worker::query()->updateOrCreate(
                ['national_id_number' => 'DEMO-MAY-HOGR'],
                [
                    'name' => 'Hogr',
                    'project_id' => $project->id,
                    'role' => Worker::ROLE_SUBCONTRACTOR,
                    'labor_kind' => Worker::LABOR_KIND_UNCLASSIFIED,
                    'daily_rate_usd' => 0,
                    'phone' => '+9647501110003',
                ],
            ),
        ];

        // Seed a few realistic cell states (idempotent via notes marker)
        $assignments = [
            // B1 MDF
            ['B1', 1, '01', ApartmentUnit::CATEGORY_MDF, ApartmentUnit::STATUS_COMPLETED, $staff[0]->id, false],
            ['B1', 1, '02', ApartmentUnit::CATEGORY_MDF, ApartmentUnit::STATUS_IN_PROGRESS, $staff[0]->id, false],
            ['B1', 1, '03', ApartmentUnit::CATEGORY_MDF, ApartmentUnit::STATUS_PENDING, null, true], // xoman
            ['B1', 2, '01', ApartmentUnit::CATEGORY_MDF, ApartmentUnit::STATUS_INSPECTED, $staff[2]->id, false],
            // B1 Laminate
            ['B1', 1, '01', ApartmentUnit::CATEGORY_LAMINATE, ApartmentUnit::STATUS_COMPLETED, $staff[1]->id, false],
            ['B1', 1, '02', ApartmentUnit::CATEGORY_LAMINATE, ApartmentUnit::STATUS_PENDING, null, true],
            // B3 Metxal
            ['B3', 1, '01', ApartmentUnit::CATEGORY_METXAL, ApartmentUnit::STATUS_IN_PROGRESS, $staff[0]->id, false],
            ['B3', 2, '02', ApartmentUnit::CATEGORY_METXAL, ApartmentUnit::STATUS_PENDING, null, true],
            ['B3', 3, '03', ApartmentUnit::CATEGORY_METXAL, ApartmentUnit::STATUS_COMPLETED, $staff[2]->id, false],
        ];

        $blocksByCode = ['B1' => $b1, 'B3' => $b3];

        foreach ($assignments as [$code, $floor, $label, $category, $status, $workerId, $crew]) {
            $unit = ApartmentUnit::query()
                ->where('project_id', $project->id)
                ->where('building_block_id', $blocksByCode[$code]->id)
                ->where('floor_number', $floor)
                ->where('unit_label', $label)
                ->where('category', $category)
                ->first();

            if (! $unit) {
                continue;
            }

            $unit->fill([
                'status' => $status,
                'assigned_worker_id' => $crew ? null : $workerId,
                'is_company_crew' => $crew,
                'notes' => self::MARKER_NOTES,
            ]);
            $unit->save();
        }
    }
}
