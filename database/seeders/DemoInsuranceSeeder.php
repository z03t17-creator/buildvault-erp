<?php

namespace Database\Seeders;

use App\Models\Payout;
use App\Models\Project;
use App\Models\ProjectReceipt;
use App\Models\RetentionHold;
use App\Models\Vault;
use App\Models\Worker;
use App\Services\ExchangeRateService;
use App\Services\PayoutService;
use App\Services\ProjectFinancialService;
use App\Services\VaultService;
use Illuminate\Database\Seeder;

/**
 * Deposit + approved payroll payouts so insurance holds appear for the demo tower.
 */
class DemoInsuranceSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            VaultSeeder::class,
            InsuranceSettingsSeeder::class,
            DemoHierarchySeeder::class,
        ]);

        $project = Project::query()->where('name', DemoHierarchySeeder::PROJECT_NAME)->first();
        $vault = Vault::query()->where('name', VaultSeeder::NAME)->first()
            ?? Vault::query()->orderBy('id')->first();

        if (! $project || ! $vault) {
            return;
        }

        // Idempotent marker: skip hold/payout seeding if demo holds already exist.
        $existing = RetentionHold::query()
            ->where('project_id', $project->id)
            ->whereHas('worker', fn ($q) => $q->whereIn('national_id_number', [
                'DEMO-ENG-001',
                'DEMO-LAB-001',
            ]))
            ->count();

        if ($existing < 2) {
            $vaultService = app(VaultService::class);
            $payoutService = app(PayoutService::class);

            // Ensure vault/pools can fund payroll payouts.
            if ((float) $vault->balance_usd < 5000) {
                $vaultService->deposit($project, 20000, $vault);
            }

            $targets = [
                'DEMO-ENG-001' => 800,
                'DEMO-LAB-001' => 350,
            ];

            $workers = Worker::query()
                ->where('project_id', $project->id)
                ->whereIn('national_id_number', array_keys($targets))
                ->get()
                ->keyBy('national_id_number');

            foreach ($targets as $nid => $amount) {
                $worker = $workers->get($nid);
                if (! $worker) {
                    continue;
                }

                $already = RetentionHold::query()
                    ->where('worker_id', $worker->id)
                    ->where('project_id', $project->id)
                    ->exists();

                if ($already) {
                    continue;
                }

                $payout = $payoutService->create([
                    'project_id' => $project->id,
                    'worker_id' => $worker->id,
                    'category' => Payout::CATEGORY_PAYROLL,
                    'amount_usd' => $amount,
                    'notes' => 'Demo payroll payout (seed)',
                    'vault_id' => $vault->id,
                ]);

                $payoutService->approve($payout);
            }

            // One matured hold for the release-to-payroll demo path.
            $lab = $workers->get('DEMO-LAB-001');
            if ($lab) {
                $hold = RetentionHold::query()
                    ->where('worker_id', $lab->id)
                    ->where('status', RetentionHold::STATUS_HOLDING)
                    ->latest('id')
                    ->first();

                if ($hold) {
                    $hold->update([
                        'hold_start' => now()->subMonths(7)->toDateString(),
                        'maturity_date' => now()->subDay()->toDateString(),
                        'status' => RetentionHold::STATUS_MATURED,
                    ]);
                }
            }
        }

        // Touch FX so IQD surfaces have a known rate in fresh demos.
        app(ExchangeRateService::class)->getUsdToIqd();

        // Phase 5: sample client receipt so project financial summary is non-zero via ledger.
        $hasReceipt = ProjectReceipt::query()
            ->where('project_id', $project->id)
            ->where('reference', 'DEMO-RCPT-001')
            ->exists();

        if (! $hasReceipt) {
            app(ProjectFinancialService::class)->recordReceipt($project, [
                'amount_iqd' => 50_000_000,
                'received_on' => now()->subDays(14)->toDateString(),
                'source' => 'Client transfer',
                'reference' => 'DEMO-RCPT-001',
                'notes' => 'Demo Phase 5 money received (seed)',
            ]);
        }
    }
}
