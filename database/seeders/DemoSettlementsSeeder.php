<?php

namespace Database\Seeders;

use App\Models\MonthlySettlement;
use App\Models\Project;
use App\Models\User;
use App\Models\Vault;
use App\Services\MonthlySettlementService;
use App\Support\Roles;
use Illuminate\Database\Seeder;

/**
 * Persist monthly settlement snapshots so Settlements / Reports have demo rows.
 * Runs last under SEED_DEMO so vault ledger totals are already populated.
 */
class DemoSettlementsSeeder extends Seeder
{
    public const MARKER_NOTE = 'DEMO-SETTLEMENT';

    public function run(): void
    {
        $vault = Vault::query()->where('name', VaultSeeder::NAME)->first()
            ?? Vault::query()->orderBy('id')->first();

        $project = Project::query()->where('name', DemoHierarchySeeder::PROJECT_NAME)->first();

        if (! $vault) {
            return;
        }

        $actor = User::role(Roles::ACCOUNTANT)->first()
            ?? User::role(Roles::SUPER_ADMIN)->first();

        if (! $actor) {
            return;
        }

        $service = app(MonthlySettlementService::class);
        $current = now()->format('Y-m');
        $previous = now()->subMonth()->format('Y-m');

        $targets = [
            ['month' => $previous, 'project_id' => null],
            ['month' => $current, 'project_id' => null],
        ];

        if ($project) {
            $targets[] = ['month' => $current, 'project_id' => (int) $project->id];
        }

        foreach ($targets as $target) {
            $exists = MonthlySettlement::query()
                ->where('vault_id', $vault->id)
                ->where('year_month', $target['month'])
                ->when(
                    $target['project_id'] === null,
                    fn ($q) => $q->whereNull('project_id'),
                    fn ($q) => $q->where('project_id', $target['project_id']),
                )
                ->exists();

            if ($exists) {
                continue;
            }

            $snapshot = $service->saveSnapshot(
                $target['month'],
                $target['project_id'],
                $actor,
                $vault,
            );

            // Tag payload so re-seeds / audits can recognize demo snapshots.
            $payload = $snapshot->payload ?? [];
            $payload['demo_marker'] = self::MARKER_NOTE;
            $snapshot->forceFill(['payload' => $payload])->save();
        }
    }
}
