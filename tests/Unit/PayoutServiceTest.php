<?php

namespace Tests\Unit;

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
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class PayoutServiceTest extends TestCase
{
    use RefreshDatabase;

    private PayoutService $service;

    private Vault $vault;

    private Project $project;

    private Worker $worker;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Http::fake([
            'api.exchangerate-api.com/*' => Http::response([
                'rates' => ['IQD' => 1310],
            ], 200),
        ]);

        $this->vault = Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 10000,
            'balance_iqd' => 13100000,
        ]);

        $this->project = Project::query()->create(['name' => 'Payout Site']);
        ProjectAllocation::query()->create([
            'project_id' => $this->project->id,
            'expenses_pool_usd' => 4500,
            'payroll_pool_usd' => 3000,
            'retention_pool_usd' => 1000,
            'penalty_pool_usd' => 500,
            'profit_pool_usd' => 1000,
        ]);

        $this->worker = Worker::query()->create([
            'project_id' => $this->project->id,
            'name' => 'Pay Worker',
        ]);

        $this->service = app(PayoutService::class);
    }

    public function test_create_pending_with_default_insurance_holdback(): void
    {
        $payout = $this->service->create([
            'project_id' => $this->project->id,
            'worker_id' => $this->worker->id,
            'category' => Payout::CATEGORY_PAYROLL,
            'amount_usd' => 1000,
        ]);

        $this->assertSame(Payout::STATUS_PENDING, $payout->status);
        $this->assertSame('100.00', (string) $payout->retention_holdback); // 10%
        $this->assertSame('1310000.00', (string) $payout->amount_iqd);
    }

    public function test_create_blocked_when_pool_exhausted(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->create([
            'project_id' => $this->project->id,
            'category' => Payout::CATEGORY_PAYROLL,
            'amount_usd' => 5000,
        ]);
    }

    public function test_approve_debits_vault_pool_and_creates_retention_hold(): void
    {
        $payout = $this->service->create([
            'project_id' => $this->project->id,
            'worker_id' => $this->worker->id,
            'category' => Payout::CATEGORY_PAYROLL,
            'amount_usd' => 1000,
        ]);

        $approved = $this->service->approve($payout);

        $this->assertSame(Payout::STATUS_APPROVED, $approved->status);
        $this->assertNotNull($approved->approved_at);

        // Cash out = 1000 - 100 holdback = 900
        $this->vault->refresh();
        $this->assertSame('9100.00', (string) $this->vault->balance_usd);

        $allocation = ProjectAllocation::query()->where('project_id', $this->project->id)->first();
        $this->assertSame('2000.00', (string) $allocation->payroll_pool_usd); // 3000 - 1000

        $hold = RetentionHold::query()->where('payout_id', $payout->id)->first();
        $this->assertNotNull($hold);
        $this->assertSame('100.00', (string) $hold->amount_usd);
        $this->assertSame(RetentionHold::STATUS_HOLDING, $hold->status);
        $this->assertTrue(
            $hold->maturity_date->equalTo(RetentionHold::maturityFrom($hold->hold_start)),
        );

        $this->assertDatabaseHas('transactions', [
            'type' => Transaction::TYPE_PAYROLL,
            'reference_id' => $payout->id,
            'amount_usd' => 900,
        ]);
    }

    public function test_reject_pending_frees_liquidity_commitment(): void
    {
        $payout = $this->service->create([
            'project_id' => $this->project->id,
            'category' => Payout::CATEGORY_EXPENSES,
            'amount_usd' => 500,
        ]);

        $this->assertSame(500.0, app(LiquidityService::class)->pendingPayoutsUsd($this->vault));

        $this->service->reject($payout, 'Duplicate request');

        $this->assertSame(Payout::STATUS_REJECTED, $payout->fresh()->status);
        $this->assertSame(0.0, app(LiquidityService::class)->pendingPayoutsUsd($this->vault));
        $this->assertSame('10000.00', (string) $this->vault->fresh()->balance_usd);
    }

    public function test_reconcile_approved_payout(): void
    {
        $payout = $this->service->create([
            'project_id' => $this->project->id,
            'category' => Payout::CATEGORY_EXPENSES,
            'amount_usd' => 200,
            'retention_holdback' => 0,
        ]);
        $this->service->approve($payout);
        $reconciled = $this->service->reconcile($payout->fresh());

        $this->assertSame(Payout::STATUS_RECONCILED, $reconciled->status);
        $this->assertNotNull($reconciled->reconciled_at);
    }

    public function test_cannot_approve_non_pending(): void
    {
        $payout = $this->service->create([
            'project_id' => $this->project->id,
            'category' => Payout::CATEGORY_EXPENSES,
            'amount_usd' => 100,
            'retention_holdback' => 0,
        ]);
        $this->service->reject($payout);

        $this->expectException(InvalidArgumentException::class);
        $this->service->approve($payout->fresh());
    }

    public function test_reconcile_applies_linked_penalty_deduction(): void
    {
        $payout = $this->service->create([
            'project_id' => $this->project->id,
            'worker_id' => $this->worker->id,
            'category' => Payout::CATEGORY_PAYROLL,
            'amount_usd' => 1000,
        ]);

        Penalty::query()->create([
            'worker_id' => $this->worker->id,
            'project_id' => $this->project->id,
            'reason' => 'Tool loss',
            'amount_usd' => 40,
            'payout_id' => $payout->id,
            'status' => Penalty::STATUS_PENDING,
        ]);

        $this->service->approve($payout);
        $this->service->reconcile($payout->fresh());

        $this->assertDatabaseHas('penalties', [
            'payout_id' => $payout->id,
            'status' => Penalty::STATUS_APPLIED,
            'deducted_from_payout' => true,
            'amount_usd' => 40,
        ]);

        $allocation = ProjectAllocation::query()->where('project_id', $this->project->id)->first();
        $this->assertSame('540.00', (string) $allocation->penalty_pool_usd);
    }
}
