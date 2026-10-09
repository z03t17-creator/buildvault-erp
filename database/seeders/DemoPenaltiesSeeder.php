<?php

namespace Database\Seeders;

use App\Models\Penalty;
use App\Models\Project;
use App\Models\Staff;
use App\Models\User;
use App\Services\PenaltyService;
use App\Support\DualCurrency;
use App\Support\Roles;
use Illuminate\Database\Seeder;

/**
 * Demo penalties against Staff (salary kind) — mirrors former Demo Engineer / Laborer.
 */
class DemoPenaltiesSeeder extends Seeder
{
    public const MARKER = 'DEMO-PEN-ENGINEER';

    public const MARKER_PENDING = 'DEMO-PEN-LABORER-PENDING';

    public function run(): void
    {
        $project = Project::query()->where('name', DemoHierarchySeeder::PROJECT_NAME)->first();
        if (! $project) {
            return;
        }

        $accountant = User::role(Roles::ACCOUNTANT)->first()
            ?? User::role(Roles::SUPER_ADMIN)->first();

        $service = app(PenaltyService::class);

        $engineer = Staff::query()->firstOrCreate(
            ['name' => 'Demo Engineer'],
            [
                'kind' => Staff::KIND_SALARY,
                'trade' => 'engineer',
                'monthly_salary' => 1200,
                'currency' => DualCurrency::USD,
                'phone' => null,
            ],
        );

        if (! Penalty::query()->where('reason', 'like', self::MARKER.'%')->exists()) {
            $service->create([
                'staff_id' => $engineer->id,
                'project_id' => $project->id,
                'type' => Penalty::TYPE_SAFETY,
                'currency' => 'IQD',
                'amount_iqd' => 65_500,
                'occurred_on' => now()->startOfMonth()->addDays(5)->toDateString(),
                'reason' => self::MARKER.' — helmet violation',
                'notes' => 'Demo: Engineer staff has this applied penalty.',
                'status' => Penalty::STATUS_APPLIED,
                'created_by' => $accountant?->id,
            ]);
        }

        $laborer = Staff::query()->firstOrCreate(
            ['name' => 'Demo Laborer Two'],
            [
                'kind' => Staff::KIND_SALARY,
                'trade' => 'laborer',
                'monthly_salary' => 600,
                'currency' => DualCurrency::USD,
                'phone' => null,
            ],
        );

        if (! Penalty::query()->where('reason', 'like', self::MARKER_PENDING.'%')->exists()) {
            $service->create([
                'staff_id' => $laborer->id,
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
