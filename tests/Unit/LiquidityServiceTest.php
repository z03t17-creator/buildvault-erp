<?php

namespace Tests\Unit;

use App\Models\Payout;
use App\Models\Project;
use App\Models\ProjectAllocation;
use App\Models\RetentionHold;
use App\Models\Vault;
use App\Models\Worker;
use App\Services\LiquidityService;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class LiquidityServiceTest extends TestCase
{
    use RefreshDatabase;

    private LiquidityService $service;

    private Vault $vault;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LiquidityService;
        $this->vault = Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 10000,
            'balance_iqd' => 13100000,
        ]);
        $this->project = Project::query()->create(['name' => 'Liquidity Site']);

        ProjectAllocation::query()->create([
            'project_id' => $this->project->id,
            'expenses_pool_usd' => 4500,
            'payroll_pool_usd' => 3000,
            'retention_pool_usd' => 1000,
            'penalty_pool_usd' => 500,
            'profit_pool_usd' => 1000,
        ]);
    }

    public function test_available_equals_vault_minus_pending_and_reserved_insurance(): void
    {
        $worker = Worker::query()->create(['name' => 'W1', 'project_id' => $this->project->id]);

        Payout::query()->create([
            'vault_id' => $this->vault->id,
            'project_id' => $this->project->id,
            'worker_id' => $worker->id,
            'category' => Payout::CATEGORY_PAYROLL,
            'amount_usd' => 500,
            'status' => Payout::STATUS_PENDING,
        ]);

        // Rejected should not count as pending.
        Payout::query()->create([
            'vault_id' => $this->vault->id,
            'project_id' => $this->project->id,
            'category' => Payout::CATEGORY_EXPENSES,
            'amount_usd' => 999,
            'status' => Payout::STATUS_REJECTED,
        ]);

        RetentionHold::query()->create([
            'vault_id' => $this->vault->id,
            'project_id' => $this->project->id,
            'worker_id' => $worker->id,
            'amount_usd' => 200,
            'hold_start' => now()->toDateString(),
            'maturity_date' => now()->addMonths(6)->toDateString(),
            'status' => RetentionHold::STATUS_HOLDING,
        ]);

        // Released insurance is no longer reserved.
        RetentionHold::query()->create([
            'vault_id' => $this->vault->id,
            'project_id' => $this->project->id,
            'worker_id' => $worker->id,
            'amount_usd' => 50,
            'hold_start' => now()->subYear()->toDateString(),
            'maturity_date' => now()->subMonths(6)->toDateString(),
            'status' => RetentionHold::STATUS_RELEASED,
            'released_at' => now(),
        ]);

        $this->assertSame(500.0, $this->service->pendingPayoutsUsd($this->vault));
        $this->assertSame(200.0, $this->service->reservedInsuranceUsd($this->vault));
        // 10000 − 500 − 200 = 9300
        $this->assertSame(9300.0, $this->service->availableUsd($this->vault));
    }

    public function test_matured_unreleased_insurance_still_reserved(): void
    {
        $worker = Worker::query()->create(['name' => 'W2', 'project_id' => $this->project->id]);

        RetentionHold::query()->create([
            'vault_id' => $this->vault->id,
            'project_id' => $this->project->id,
            'worker_id' => $worker->id,
            'amount_usd' => 300,
            'hold_start' => now()->subMonths(7)->toDateString(),
            'maturity_date' => now()->subMonth()->toDateString(),
            'status' => RetentionHold::STATUS_MATURED,
        ]);

        $this->assertSame(300.0, $this->service->reservedInsuranceUsd($this->vault));
        $this->assertSame(9700.0, $this->service->availableUsd($this->vault));
    }

    public function test_can_pay_allows_when_within_available_and_pool(): void
    {
        $result = $this->service->canPay(
            $this->project,
            Payout::CATEGORY_PAYROLL,
            1000,
            $this->vault,
        );

        $this->assertTrue($result['allowed']);
        $this->assertSame([], $result['reasons']);
        $this->assertSame(10000.0, $result['available_usd']);
        $this->assertSame(3000.0, $result['pool_available_usd']);
    }

    public function test_blocks_when_request_exceeds_available_liquidity(): void
    {
        $worker = Worker::query()->create(['name' => 'W3', 'project_id' => $this->project->id]);

        Payout::query()->create([
            'vault_id' => $this->vault->id,
            'project_id' => $this->project->id,
            'worker_id' => $worker->id,
            'category' => Payout::CATEGORY_PAYROLL,
            'amount_usd' => 8000,
            'status' => Payout::STATUS_PENDING,
        ]);

        RetentionHold::query()->create([
            'vault_id' => $this->vault->id,
            'project_id' => $this->project->id,
            'worker_id' => $worker->id,
            'amount_usd' => 1500,
            'hold_start' => now()->toDateString(),
            'maturity_date' => now()->addMonths(6)->toDateString(),
            'status' => RetentionHold::STATUS_HOLDING,
        ]);

        // available = 10000 − 8000 − 1500 = 500
        $result = $this->service->canPay(
            $this->project,
            Payout::CATEGORY_PAYROLL,
            600,
            $this->vault,
        );

        $this->assertFalse($result['allowed']);
        $this->assertSame(500.0, $result['available_usd']);
        $this->assertNotEmpty($result['reasons']);
        $this->assertStringContainsString('available liquidity', $result['reasons'][0]);
    }

    public function test_blocks_when_category_pool_exhausted(): void
    {
        // Vault has plenty (10000), but penalty pool is only 500.
        $result = $this->service->canPay(
            $this->project,
            Payout::CATEGORY_PENALTY,
            600,
            $this->vault,
        );

        $this->assertFalse($result['allowed']);
        $this->assertSame(500.0, $result['pool_available_usd']);
        $this->assertTrue(
            collect($result['reasons'])->contains(fn (string $r) => str_contains($r, 'penalty pool')),
        );
    }

    public function test_assert_can_pay_throws_when_blocked(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->assertCanPay(
            $this->project,
            Payout::CATEGORY_EXPENSES,
            5000, // expenses pool is 4500
            $this->vault,
        );
    }

    public function test_pool_available_zero_without_allocation_row(): void
    {
        $orphan = Project::query()->create(['name' => 'No Allocation']);

        $this->assertSame(0.0, $this->service->poolAvailableUsd($orphan, Payout::CATEGORY_PAYROLL));

        $result = $this->service->canPay($orphan, Payout::CATEGORY_PAYROLL, 10, $this->vault);
        $this->assertFalse($result['allowed']);
    }

    public function test_rejects_unknown_category(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->canPay($this->project, 'not-a-category', 10, $this->vault);
    }

    public function test_rejects_non_positive_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->canPay($this->project, Payout::CATEGORY_PAYROLL, 0, $this->vault);
    }
}
