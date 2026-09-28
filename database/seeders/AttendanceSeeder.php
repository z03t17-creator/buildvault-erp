<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Floor;
use App\Models\Penalty;
use App\Models\Project;
use App\Models\Worker;
use App\Services\AttendanceService;
use Illuminate\Database\Seeder;

/**
 * Sample attendance + penalty rows for demo workers (idempotent patterns).
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

        // Build a richer month window ending today so payroll totals are visible.
        $days = collect(range(0, 11))->map(fn (int $i) => now()->subDays(11 - $i)->toDateString());

        $patterns = [
            'DEMO-ENG-001' => [
                // present + OT, late, present…
                ['08:00', '19:00'], // OT
                ['08:10', '17:05'], // late
                ['08:00', '17:00'],
                null, // skip → leave below
                ['08:00', '18:30'],
                ['08:05', '17:00'],
                ['07:55', '17:10'],
                ['08:00', '17:00'],
                ['08:40', '17:00'], // late
                ['08:00', '19:30'], // OT
                ['08:00', '17:00'],
                ['08:00', '17:00'],
            ],
            'DEMO-SUP-001' => [
                ['08:00', '17:00'],
                ['08:45', '17:00'],
                ['08:00', '18:00'],
                ['08:00', '17:00'],
                ['09:00', '17:00'],
                ['08:00', '17:00'],
                ['08:00', '17:00'],
                null,
                ['08:00', '17:00'],
                ['08:20', '17:00'],
                ['08:00', '17:00'],
                ['08:00', '17:00'],
            ],
            'DEMO-LAB-001' => [
                ['07:55', '17:10'],
                ['07:50', '18:00'],
                ['08:00', '17:00'],
                ['08:00', '17:00'],
                ['08:30', '17:00'],
                ['08:00', '19:00'],
                ['08:00', '17:00'],
                ['08:00', '17:00'],
                ['08:00', '17:00'],
                ['07:58', '17:05'],
                ['08:00', '17:00'],
                ['08:15', '17:00'],
            ],
            'DEMO-LAB-002' => [
                ['08:00', '17:00'],
                null, // sick
                ['08:00', '17:00'],
                ['08:00', '17:00'],
                null, // unexcused handled below
                ['08:00', '17:00'],
                ['08:50', '17:00'],
                ['08:00', '17:00'],
                ['08:00', '18:45'],
                ['08:00', '17:00'],
                ['08:00', '17:00'],
                ['08:00', '17:00'],
            ],
            'DEMO-LAB-003' => [
                ['08:00', '17:00'],
                ['08:00', '17:00'],
                ['08:25', '17:00'],
                ['08:00', '19:00'],
                ['08:00', '17:00'],
                ['08:00', '17:00'],
                ['08:00', '17:00'],
                ['08:00', '17:00'],
                ['08:00', '17:00'],
                null,
                ['08:00', '17:00'],
                ['08:00', '17:00'],
            ],
            'DEMO-SUB-001' => [
                ['08:00', '17:00'],
                ['08:00', '18:00'],
                ['08:00', '17:00'],
                ['08:00', '17:00'],
                ['08:00', '17:00'],
                ['08:00', '17:00'],
                ['08:10', '17:00'],
                ['08:00', '17:00'],
                ['08:00', '17:00'],
                ['08:00', '17:00'],
                ['08:00', '19:00'],
                ['08:00', '17:00'],
            ],
            'DEMO-LAB-004' => [
                ['07:30', '17:00'],
                ['07:30', '17:00'],
                ['07:30', '19:00'],
                ['07:30', '17:00'],
                ['08:00', '17:00'],
                ['07:30', '17:00'],
                ['07:30', '17:00'],
                ['07:30', '17:00'],
                ['07:30', '17:00'],
                ['07:30', '17:00'],
                ['07:30', '17:00'],
                ['07:30', '17:00'],
            ],
        ];

        foreach ($patterns as $nid => $dayPatterns) {
            $worker = $workers->get($nid);
            if (! $worker) {
                continue;
            }

            foreach ($days as $i => $date) {
                $slot = $dayPatterns[$i] ?? null;
                if ($slot === null) {
                    continue;
                }

                $service->record($worker, $date, [
                    'floor_id' => $floor?->id,
                    'check_in' => $slot[0],
                    'check_out' => $slot[1],
                ]);
            }
        }

        // Explicit leave / absence samples (no check-in).
        $this->seedStatusDay($workers->get('DEMO-ENG-001'), $days[3], $floor?->id, Attendance::STATUS_LEAVE_PAID);
        $this->seedStatusDay($workers->get('DEMO-LAB-002'), $days[1], $floor?->id, Attendance::STATUS_LEAVE_SICK);
        $this->seedStatusDay($workers->get('DEMO-LAB-002'), $days[4], $floor?->id, Attendance::STATUS_ABSENT_UNEXCUSED);
        $this->seedStatusDay($workers->get('DEMO-SUP-001'), $days[7], $floor?->id, Attendance::STATUS_LEAVE_PAID);
        $this->seedStatusDay($workers->get('DEMO-LAB-003'), $days[9], $floor?->id, Attendance::STATUS_ABSENT_UNEXCUSED);

        // Manual penalty rows visible on Penalties + absorbed into payroll context.
        $labOne = $workers->get('DEMO-LAB-001');
        $labTwo = $workers->get('DEMO-LAB-002');
        if ($labOne) {
            Penalty::query()->updateOrCreate(
                [
                    'worker_id' => $labOne->id,
                    'reason' => 'Demo: safety PPE reminder',
                ],
                [
                    'project_id' => $project->id,
                    'floor_id' => $floor?->id,
                    'amount_usd' => 15,
                    'status' => Penalty::STATUS_PENDING,
                ],
            );
        }
        if ($labTwo) {
            Penalty::query()->updateOrCreate(
                [
                    'worker_id' => $labTwo->id,
                    'reason' => 'Demo: unexcused site departure',
                ],
                [
                    'project_id' => $project->id,
                    'floor_id' => $floor?->id,
                    'amount_usd' => 25,
                    'status' => Penalty::STATUS_PENDING,
                ],
            );
        }
    }

    private function seedStatusDay(?Worker $worker, string $date, ?int $floorId, string $status): void
    {
        if (! $worker) {
            return;
        }

        $row = Attendance::query()
            ->where('worker_id', $worker->id)
            ->whereDate('date', $date)
            ->first() ?? new Attendance([
                'worker_id' => $worker->id,
                'date' => $date,
            ]);

        $row->fill([
            'floor_id' => $floorId,
            'check_in' => null,
            'check_out' => null,
            'late_minutes' => 0,
            'overtime_hours' => 0,
            'status' => $status,
        ]);
        $row->save();
    }
}
