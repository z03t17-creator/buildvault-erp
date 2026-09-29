<?php

namespace Database\Seeders;

use App\Models\Penalty;
use App\Models\Project;
use App\Models\User;
use App\Models\Worker;
use App\Services\PenaltyService;
use App\Support\Roles;
use Illuminate\Database\Seeder;

/**
 * Demo penalties: applied safety hit on Engineer + pending late on Laborer.
 */
class DemoPenaltiesSeeder extends Seeder
{
    public const MARKER = 'DEMO-PEN-ENGINEER';

    public const MARKER_PENDING = 'DEMO-PEN-LABORER-PENDING';

    public function run(): void
    {
        // Expect hierarchy/users/advances/insurance seeders already run (no nested re-seed).
        $project = Project::query()->where('name', DemoHierarchySeeder::PROJECT_NAME)->first();
        if (! $project) {
            return;
        }

        $accountant = User::role(Roles::ACCOUNTANT)->first()
            ?? User::role(Roles::SUPER_ADMIN)->first();

        $service = app(PenaltyService::class);

        $engineer = Worker::query()
            ->where('project_id', $project->id)
            ->where('name', 'Demo Engineer')
            ->first();

        if ($engineer && ! Penalty::query()->where('reason', 'like', self::MARKER.'%')->exists()) {
            $service->create([
                'worker_id' => $engineer->id,
                'project_id' => $project->id,
                'type' => Penalty::TYPE_SAFETY,
                'currency' => 'IQD',
                'amount_iqd' => 65_500, // Fine Amount IQD (unused USD = 0)
                'occurred_on' => now()->startOfMonth()->addDays(5)->toDateString(),
                'reason' => self::MARKER.' — helmet violation',
                'notes' => 'Demo: Engineer has advance + insurance hold + this applied penalty.',
                'status' => Penalty::STATUS_APPLIED,
                'created_by' => $accountant?->id,
            ]);
        }

        $laborer = Worker::query()
            ->where('project_id', $project->id)
            ->where('name', 'Demo Laborer Two')
            ->first();

        if ($laborer && ! Penalty::query()->where('reason', 'like', self::MARKER_PENDING.'%')->exists()) {
            $service->create([
                'worker_id' => $laborer->id,
                'project_id' => $project->id,
                'type' => Penalty::TYPE_LATE,
                'currency' => 'IQD',
                'amount_iqd' => 26_200,
                'occurred_on' => now()->subDays(2)->toDateString(),
                'reason' => self::MARKER_PENDING.' — late arrival awaiting payroll deduct',
                'notes' => 'Demo: pending penalty for Phase 20 role QA workflows.',
                'status' => Penalty::STATUS_PENDING,
                'created_by' => $accountant?->id,
            ]);
        }
    }
}
