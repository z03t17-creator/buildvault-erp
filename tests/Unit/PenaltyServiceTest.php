<?php

namespace Tests\Unit;

use App\Models\Payout;
use App\Models\Penalty;
use App\Models\Project;
use App\Models\ProjectAllocation;
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

class PenaltyServiceTest extends TestCase
{
    use RefreshDatabase;

    private PenaltyService $penalties;

    private PayoutService $payouts;

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

        $this->project = Project::query()->create(['name' => 'Penalty Site']);
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
            'name' => 'Penalty Worker',
        ]);

        $this->penalties = app(PenaltyService::class);
        $this->payouts = app(PayoutService::class);
    }

    public function test_create_pending_penalty_linked_to_worker(): void
    {
        $penalty = $this->penalties->create([
            'worker_id' => $this->worker->id,
            'project_id' => $this->project->id,
            'reason' => 'Damaged tools',
            'amount_usd' => 50,
        ]);

        $this->assertSame(Penalty::STATUS_PENDING, $penalty->status);
        $this->assertSame((int) $this->worker->id, (int) $penalty->worker_id);
        $this->assertFalse($penalty->deducted_from_payout);
        $this->assertNull($penalty->payout_id);
    }

    public function test_waive_pending_penalty(): void
    {
        $penalty = $this->penalties->create([
            'worker_id' => $this->worker->id,
            'project_id' => $this->project->id,
            'reason' => 'Late arrival',
            'amount_usd' => 25,
        ]);

        $waived = $this->penalties->waive($penalty, 'First offense');

        $this->assertSame(Penalty::STATUS_WAIVED, $waived->status);
        $this->assertStringContainsString('[waived: First offense]', $waived->reason);
    }

    public function test_link_to_payout_and_apply_on_reconcile(): void
    {
        $payout = $this->payouts->create([
            'project_id' => $this->project->id,
            'worker_id' => $this->worker->id,
            'category' => Payout::CATEGORY_PAYROLL,
            'amount_usd' => 1000,
        ]);

        $penalty = $this->penalties->create([
            'worker_id' => $this->worker->id,
            'project_id' => $this->project->id,
            'reason' => 'Safety violation',
            'amount_usd' => 75,
            'payout_id' => $payout->id,
        ]);

        $this->assertSame((int) $payout->id, (int) $penalty->payout_id);

        $this->payouts->approve($payout);
        $reconciled = $this->payouts->reconcile($payout->fresh());

        $this->assertSame(Payout::STATUS_RECONCILED, $reconciled->status);

        $penalty->refresh();
        $this->assertSame(Penalty::STATUS_APPLIED, $penalty->status);
        $this->assertTrue($penalty->deducted_from_payout);

        $allocation = ProjectAllocation::query()->where('project_id', $this->project->id)->first();
        // Starting 500 + 75 deduction credit
        $this->assertSame('575.00', (string) $allocation->penalty_pool_usd);

        $this->assertDatabaseHas('transactions', [
            'type' => Transaction::TYPE_ADJUSTMENT,
            'reference_id' => $payout->id,
            'amount_usd' => 75,
        ]);
    }

    public function test_link_rejects_other_worker_payout(): void
    {
        $other = Worker::query()->create([
            'project_id' => $this->project->id,
            'name' => 'Other Worker',
        ]);

        $payout = $this->payouts->create([
            'project_id' => $this->project->id,
            'worker_id' => $other->id,
            'category' => Payout::CATEGORY_PAYROLL,
            'amount_usd' => 500,
        ]);

        $penalty = $this->penalties->create([
            'worker_id' => $this->worker->id,
            'project_id' => $this->project->id,
            'reason' => 'Mismatch',
            'amount_usd' => 10,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->penalties->linkToPayout($penalty, $payout);
    }

    public function test_cannot_create_zero_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->penalties->create([
            'worker_id' => $this->worker->id,
            'project_id' => $this->project->id,
            'reason' => 'Empty',
            'amount_usd' => 0,
        ]);
    }
}
