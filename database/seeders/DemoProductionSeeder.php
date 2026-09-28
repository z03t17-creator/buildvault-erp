<?php

namespace Database\Seeders;

use App\Models\ProductionRecord;
use App\Models\Project;
use App\Models\User;
use App\Models\Worker;
use App\Services\ProductionRecordService;
use App\Support\Roles;
use Illuminate\Database\Seeder;

/**
 * Demo production (work) rows on Zhako Demo Tower (idempotent by notes markers).
 */
class DemoProductionSeeder extends Seeder
{
    public const MARKERS = [
        'DEMO-PROD-ENGINEER',
        'DEMO-PROD-LABORER',
        'DEMO-PROD-CUSTOM',
    ];

    public function run(): void
    {
        $this->call([
            DemoHierarchySeeder::class,
            DemoUsersSeeder::class,
        ]);

        $project = Project::query()->where('name', DemoHierarchySeeder::PROJECT_NAME)->first();
        if (! $project) {
            return;
        }

        $existing = ProductionRecord::query()
            ->where('project_id', $project->id)
            ->where(function ($q) {
                foreach (self::MARKERS as $marker) {
                    $q->orWhere('notes', 'like', $marker.'%');
                }
            })
            ->count();

        if ($existing >= count(self::MARKERS)) {
            return;
        }

        $accountant = User::role(Roles::ACCOUNTANT)->first()
            ?? User::role(Roles::SUPER_ADMIN)->first();

        $engineer = Worker::query()
            ->where('project_id', $project->id)
            ->where('name', 'Demo Engineer')
            ->first();
        $laborer = Worker::query()
            ->where('project_id', $project->id)
            ->where('name', 'Demo Laborer One')
            ->first();

        if (! $engineer || ! $laborer) {
            return;
        }

        $service = app(ProductionRecordService::class);

        if (! ProductionRecord::query()->where('notes', 'like', self::MARKERS[0].'%')->exists()) {
            $service->create([
                'worker_id' => $engineer->id,
                'project_id' => $project->id,
                'unit_type' => ProductionRecord::UNIT_APARTMENT,
                'assigned' => 12,
                'completed' => 5,
                'received' => 4,
                'recorded_on' => now()->subDays(3)->toDateString(),
                'notes' => self::MARKERS[0].' — apartments finishing (remaining = assigned − completed)',
            ], $accountant);
        }

        if (! ProductionRecord::query()->where('notes', 'like', self::MARKERS[1].'%')->exists()) {
            $service->create([
                'worker_id' => $laborer->id,
                'project_id' => $project->id,
                'unit_type' => ProductionRecord::UNIT_FLOOR,
                'assigned' => 8,
                'completed' => 8,
                'received' => 6,
                'recorded_on' => now()->subDays(7)->toDateString(),
                'notes' => self::MARKERS[1].' — floors plastered (100% progress)',
            ], $accountant);
        }

        if (! ProductionRecord::query()->where('notes', 'like', self::MARKERS[2].'%')->exists()) {
            $service->create([
                'worker_id' => $engineer->id,
                'project_id' => $project->id,
                'unit_type' => ProductionRecord::UNIT_OTHER,
                'unit_label' => 'Parking bay',
                'assigned' => 20,
                'completed' => 7,
                'received' => 5,
                'recorded_on' => now()->subDay()->toDateString(),
                'notes' => self::MARKERS[2].' — custom unit type example',
            ], $accountant);
        }
    }
}
