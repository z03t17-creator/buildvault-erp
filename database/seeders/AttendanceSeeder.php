<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Floor;
use App\Models\Project;
use App\Models\Worker;
use App\Services\AttendanceService;
use Illuminate\Database\Seeder;

/**
 * Sample attendance rows for demo workers (recalculates late/OT via service).
 */
class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DemoHierarchySeeder::class);

        $project = Project::query()->where('name', DemoHierarchySeeder::PROJECT_NAME)->first();
        if (! $project) {
            return;
        }

        $floor = Floor::query()
            ->whereHas('tower', fn ($q) => $q->where('project_id', $project->id))
            ->where('name', 'Floor 1')
            ->first();

        $service = app(AttendanceService::class);
        $workers = Worker::query()->where('project_id', $project->id)->get()->keyBy('national_id_number');
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        $samples = [
            // On time + OT yesterday
            [
                'nid' => 'DEMO-ENG-001',
                'date' => $yesterday,
                'check_in' => '08:00',
                'check_out' => '19:00',
            ],
            // Late today
            [
                'nid' => 'DEMO-SUP-001',
                'date' => $today,
                'check_in' => '08:45',
                'check_out' => '17:00',
            ],
            // Present today
            [
                'nid' => 'DEMO-LAB-001',
                'date' => $today,
                'check_in' => '07:55',
                'check_out' => '17:10',
            ],
        ];

        foreach ($samples as $sample) {
            $worker = $workers->get($sample['nid']);
            if (! $worker) {
                continue;
            }

            $service->record($worker, $sample['date'], [
                'floor_id' => $floor?->id,
                'check_in' => $sample['check_in'],
                'check_out' => $sample['check_out'],
            ]);
        }

        // Explicit leave sample (no check-in)
        $laborerTwo = $workers->get('DEMO-LAB-002');
        if ($laborerTwo) {
            Attendance::query()->updateOrCreate(
                [
                    'worker_id' => $laborerTwo->id,
                    'date' => $today,
                ],
                [
                    'floor_id' => $floor?->id,
                    'check_in' => null,
                    'check_out' => null,
                    'late_minutes' => 0,
                    'overtime_hours' => 0,
                    'status' => Attendance::STATUS_LEAVE_SICK,
                ],
            );
        }
    }
}
