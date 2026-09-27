<?php

namespace Tests\Feature;

use App\Models\Payout;
use App\Models\Penalty;
use App\Models\Project;
use App\Models\ProjectAllocation;
use App\Models\RetentionHold;
use App\Models\Transaction;
use App\Models\Vault;
use App\Models\Worker;
use App\Services\ExchangeRateService;
use App\Services\LiquidityService;
use App\Services\PayoutService;
use App\Services\PenaltyService;
use App\Services\RetentionHoldService;
use App\Services\VaultService;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 3.8 checkpoint: deposit 10k → split → payout approve →
 * 10% insurance hold + 6mo timer → penalty deduct on reconcile.
 */
class Phase3ReviewCheckpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase_3_money_flow_checkpoint(): void
    {
        Cache::flush();
        Http::fake([
            'api.exchangerate-api.com/*' => Http::response([
                'rates' => ['IQD' => 1310],
            ], 200),
        ]);

        $vault = Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 0,
            'balance_iqd' => 0,
        ]);

        $project = Project::query()->create(['name' => 'Phase 3 Checkpoint']);
        $worker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Checkpoint Worker',
        ]);

        $vaultService = app(VaultService::class);
        $penaltyService = app(PenaltyService::class);
        $payoutService = app(PayoutService::class);
        $retentionService = app(RetentionHoldService::class);

        // 1) Deposit 10,000 USD → 5-way split
        $deposit = $vaultService->deposit($project, 10000, $vault);

        $this->assertSame(10000.0, $deposit['amount_usd']);
        $this->assertSame(13100000.0, $deposit['amount_iqd']);
        $this->assertSame(4500.0, $deposit['split']['expenses_usd']);
        $this->assertSame(3000.0, $deposit['split']['payroll_usd']);
        $this->assertSame(1000.0, $deposit['split']['retention_usd']);
        $this->assertSame(500.0, $deposit['split']['penalty_usd']);
        $this->assertSame(1000.0, $deposit['split']['profit_usd']);

        $vault->refresh();
        $this->assertSame('10000.00', (string) $vault->balance_usd);

        $allocation = ProjectAllocation::query()->where('project_id', $project->id)->firstOrFail();
        $this->assertSame('4500.00', (string) $allocation->expenses_pool_usd);
        $this->assertSame('3000.00', (string) $allocation->payroll_pool_usd);
        $this->assertSame('1000.00', (string) $allocation->retention_pool_usd);
        $this->assertSame('500.00', (string) $allocation->penalty_pool_usd);
        $this->assertSame('1000.00', (string) $allocation->profit_pool_usd);

        // 2) Payroll payout 1000 → approve → 10% insurance hold + 6mo maturity
        $payout = $payoutService->create([
            'project_id' => $project->id,
            'worker_id' => $worker->id,
            'category' => Payout::CATEGORY_PAYROLL,
            'amount_usd' => 1000,
        ]);

        $this->assertSame('100.00', (string) $payout->retention_holdback);

        $approved = $payoutService->approve($payout);
        $this->assertSame(Payout::STATUS_APPROVED, $approved->status);

        $hold = RetentionHold::query()->where('payout_id', $payout->id)->firstOrFail();
        $this->assertSame(RetentionHold::STATUS_HOLDING, $hold->status);
        $this->assertSame('100.00', (string) $hold->amount_usd);
        $this->assertTrue(
            $hold->maturity_date->equalTo(RetentionHold::maturityFrom($hold->hold_start)),
        );
        $this->assertSame(
            $hold->hold_start->copy()->addMonthsNoOverflow(6)->toDateString(),
            $hold->maturity_date->toDateString(),
        );

        // Cash out 900; vault 10000 − 900 = 9100; payroll pool 3000 − 1000 = 2000
        $vault->refresh();
        $this->assertSame('9100.00', (string) $vault->balance_usd);
        $allocation->refresh();
        $this->assertSame('2000.00', (string) $allocation->payroll_pool_usd);
        $this->assertSame(100.0, app(LiquidityService::class)->reservedInsuranceUsd($vault));

        // 3) Penalty linked to payout → deduct on reconcile
        $penalty = $penaltyService->create([
            'worker_id' => $worker->id,
            'project_id' => $project->id,
            'reason' => 'Checkpoint safety fine',
            'amount_usd' => 50,
            'payout_id' => $payout->id,
        ]);

        $reconciled = $payoutService->reconcile($payout->fresh());
        $this->assertSame(Payout::STATUS_RECONCILED, $reconciled->status);

        $penalty->refresh();
        $this->assertSame(Penalty::STATUS_APPLIED, $penalty->status);
        $this->assertTrue($penalty->deducted_from_payout);

        $allocation->refresh();
        $this->assertSame('550.00', (string) $allocation->penalty_pool_usd); // 500 + 50

        $this->assertDatabaseHas('transactions', [
            'type' => Transaction::TYPE_ADJUSTMENT,
            'reference_id' => $payout->id,
            'amount_usd' => 50,
        ]);

        // 4) Maturity timer: force due date → artisan command → matured → release to payroll
        $hold->maturity_date = now()->subDay()->toDateString();
        $hold->save();

        Artisan::call('retention:check-maturity');
        $this->assertSame(RetentionHold::STATUS_MATURED, $hold->fresh()->status);

        $released = $retentionService->release($hold->fresh());
        $this->assertSame(RetentionHold::STATUS_RELEASED, $released->status);

        $allocation->refresh();
        // payroll was 2000; +100 released insurance
        $this->assertSame('2100.00', (string) $allocation->payroll_pool_usd);
        $this->assertSame(0.0, app(LiquidityService::class)->reservedInsuranceUsd($vault));
    }
}
