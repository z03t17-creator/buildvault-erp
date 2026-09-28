<?php

namespace Database\Seeders;

use App\Models\EmployeeAdvance;
use App\Models\Project;
use App\Models\User;
use App\Models\Worker;
use App\Services\EmployeeAdvanceService;
use App\Support\Roles;
use Illuminate\Database\Seeder;

/**
 * Demo employee advances (سلفە) on Zhako Demo Tower (idempotent by reason markers).
 */
class DemoAdvancesSeeder extends Seeder
{
    public const MARKERS = [
        'DEMO-ADV-ENGINEER',
        'DEMO-ADV-LABORER',
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

        $existing = EmployeeAdvance::query()
            ->where('project_id', $project->id)
            ->where(function ($q) {
                foreach (self::MARKERS as $marker) {
                    $q->orWhere('reason', 'like', $marker.'%');
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

        $service = app(EmployeeAdvanceService::class);

        if (! EmployeeAdvance::query()->where('reason', 'like', self::MARKERS[0].'%')->exists()) {
            $service->create([
                'worker_id' => $engineer->id,
                'project_id' => $project->id,
                'amount_iqd' => 500_000,
                'remaining_iqd' => 500_000,
                'advanced_on' => now()->subDays(12)->toDateString(),
                'reason' => self::MARKERS[0].' — family emergency cash',
                'repayment_method' => EmployeeAdvance::REPAY_PAYROLL,
                'notes' => 'Demo open advance deducted from payroll net.',
            ], $accountant);
        }

        if (! EmployeeAdvance::query()->where('reason', 'like', self::MARKERS[1].'%')->exists()) {
            $service->create([
                'worker_id' => $laborer->id,
                'project_id' => $project->id,
                'amount_iqd' => 200_000,
                'remaining_iqd' => 100_000,
                'advanced_on' => now()->subDays(20)->toDateString(),
                'reason' => self::MARKERS[1].' — tools deposit',
                'repayment_method' => EmployeeAdvance::REPAY_PAYROLL,
                'notes' => 'Demo partially repaid advance (100k remaining).',
            ], $accountant);
        }
    }
}
