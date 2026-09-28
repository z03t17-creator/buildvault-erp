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
 * Demo penalty on Demo Engineer so one worker shows penalty + advance + insurance.
 */
class DemoPenaltiesSeeder extends Seeder
{
    public const MARKER = 'DEMO-PEN-ENGINEER';

    public function run(): void
    {
        $this->call([
            DemoHierarchySeeder::class,
            DemoUsersSeeder::class,
            DemoAdvancesSeeder::class,
            DemoInsuranceSeeder::class,
        ]);

        $project = Project::query()->where('name', DemoHierarchySeeder::PROJECT_NAME)->first();
        if (! $project) {
            return;
        }

        if (Penalty::query()->where('reason', 'like', self::MARKER.'%')->exists()) {
            return;
        }

        $engineer = Worker::query()
            ->where('project_id', $project->id)
            ->where('name', 'Demo Engineer')
            ->first();

        if (! $engineer) {
            return;
        }

        $accountant = User::role(Roles::ACCOUNTANT)->first()
            ?? User::role(Roles::SUPER_ADMIN)->first();

        app(PenaltyService::class)->create([
            'worker_id' => $engineer->id,
            'project_id' => $project->id,
            'type' => Penalty::TYPE_SAFETY,
            'amount_iqd' => 65_500, // 50 USD @ 1310
            'occurred_on' => now()->startOfMonth()->addDays(5)->toDateString(),
            'reason' => self::MARKER.' — helmet violation',
            'notes' => 'Demo Phase 8: Engineer has advance + insurance hold + this penalty.',
            'status' => Penalty::STATUS_APPLIED,
            'created_by' => $accountant?->id,
        ]);
    }
}
