<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\ProjectAllocation;
use App\Models\Transaction;
use App\Models\Vault;
use App\Services\ExchangeRateService;
use App\Services\VaultService;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class VaultServiceTest extends TestCase
{
    use RefreshDatabase;

    private VaultService $service;

    private Vault $vault;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->vault = Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 0,
            'balance_iqd' => 0,
        ]);

        $this->service = app(VaultService::class);
    }

    public function test_split_math_default_percentages_sum_to_deposit(): void
    {
        $project = Project::query()->create(['name' => 'Default Split']);
        $pct = $this->service->percentagesFor($project);

        $this->assertSame(45.0, $pct['expenses']);
        $this->assertSame(30.0, $pct['payroll']);
        $this->assertSame(10.0, $pct['retention']);
        $this->assertSame(5.0, $pct['penalty']);
        $this->assertSame(10.0, $pct['profit']);

        $split = $this->service->splitAmount(10000, $pct);

        $this->assertSame(4500.0, $split['expenses_usd']);
        $this->assertSame(3000.0, $split['payroll_usd']);
        $this->assertSame(1000.0, $split['retention_usd']);
        $this->assertSame(500.0, $split['penalty_usd']);
        $this->assertSame(1000.0, $split['profit_usd']);

        $sum = array_sum($split);
        $this->assertEqualsWithDelta(10000.0, $sum, 0.001);
    }

    public function test_split_uses_project_percentage_overrides(): void
    {
        $project = Project::query()->create([
            'name' => 'Custom Split',
            'allocation_expenses_pct' => 40,
            'allocation_payroll_pct' => 35,
            'allocation_insurance_pct' => 10,
            'allocation_penalty_pct' => 5,
            'allocation_profit_pct' => 10,
        ]);

        $split = $this->service->splitAmount(1000, $this->service->percentagesFor($project));

        $this->assertSame(400.0, $split['expenses_usd']);
        $this->assertSame(350.0, $split['payroll_usd']);
        $this->assertSame(100.0, $split['retention_usd']);
        $this->assertSame(50.0, $split['penalty_usd']);
        $this->assertSame(100.0, $split['profit_usd']);
        $this->assertEqualsWithDelta(1000.0, array_sum($split), 0.001);
    }

    public function test_split_remainder_cents_land_on_profit(): void
    {
        // 100.01 * 45% = 45.0045 → 45.00; remainder absorbed by profit.
        $pct = [
            'expenses' => 45,
            'payroll' => 30,
            'retention' => 10,
            'penalty' => 5,
            'profit' => 10,
        ];
        $split = $this->service->splitAmount(100.01, $pct);
        $this->assertEqualsWithDelta(100.01, array_sum($split), 0.001);
    }

    public function test_deposit_is_qasa_single_leg_usd_without_fx_fill(): void
    {
        $project = Project::query()->create(['name' => 'FX Site']);

        $result = $this->service->deposit($project, 10000, $this->vault);

        $this->assertSame(0.0, $result['rate']);
        $this->assertSame(10000.0, $result['amount_usd']);
        $this->assertSame(0.0, $result['amount_iqd']); // unused side stays 0

        $this->vault->refresh();
        $this->assertSame('10000.00', (string) $this->vault->balance_usd);
        $this->assertSame('0.00', (string) $this->vault->balance_iqd);

        $allocation = ProjectAllocation::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($allocation);
        $this->assertSame('4500.00', (string) $allocation->expenses_pool_usd);
        $this->assertSame('3000.00', (string) $allocation->payroll_pool_usd);
        $this->assertSame('1000.00', (string) $allocation->retention_pool_usd);
        $this->assertSame('500.00', (string) $allocation->penalty_pool_usd);
        $this->assertSame('1000.00', (string) $allocation->profit_pool_usd);

        $poolSum = (float) $allocation->expenses_pool_usd
            + (float) $allocation->payroll_pool_usd
            + (float) $allocation->retention_pool_usd
            + (float) $allocation->penalty_pool_usd
            + (float) $allocation->profit_pool_usd;
        $this->assertEqualsWithDelta(10000.0, $poolSum, 0.001);

        $this->assertSame(Transaction::TYPE_DEPOSIT, $result['deposit_transaction']->type);
        $this->assertSame(Transaction::TYPE_ALLOCATION, $result['allocation_transaction']->type);
        $this->assertSame(
            $result['deposit_transaction']->id,
            $result['allocation_transaction']->reference_id,
        );

        $this->assertDatabaseCount('transactions', 2);

        $this->assertArrayHasKey('ability', $result);
        $this->assertSame(10000.0, $result['ability']['vault_balance_usd']);
        $this->assertSame(1000.0, $result['ability']['pools']['retention_usd']);
    }

    public function test_iqd_deposit_does_not_allocate_usd_pools(): void
    {
        $project = Project::query()->create(['name' => 'IQD Site']);
        $result = $this->service->deposit(
            $project,
            0,
            $this->vault,
            null,
            'IQD money in',
            Transaction::TYPE_DEPOSIT,
            true,
            null,
            null,
            'IQD',
            131000,
        );

        $this->assertSame(0.0, $result['amount_usd']);
        $this->assertSame(131000.0, $result['amount_iqd']);
        $this->assertSame('131000.00', (string) $this->vault->fresh()->balance_iqd);
        $this->assertSame('0.00', (string) $this->vault->fresh()->balance_usd);
        $this->assertSame(0.0, $result['split']['expenses_usd']);
    }

    public function test_second_deposit_accumulates_pools(): void
    {
        $project = Project::query()->create(['name' => 'Accumulate']);
        $this->service->deposit($project, 1000, $this->vault);
        $this->service->deposit($project, 1000, $this->vault);

        $allocation = ProjectAllocation::query()->where('project_id', $project->id)->first();
        $this->assertSame('900.00', (string) $allocation->expenses_pool_usd); // 2 × 450
        $this->assertSame('200.00', (string) $allocation->retention_pool_usd); // 2 × 100
        $this->assertSame('2000.00', (string) $this->vault->fresh()->balance_usd);
        $this->assertDatabaseCount('transactions', 4);
    }

    public function test_rejects_non_positive_deposit(): void
    {
        $project = Project::query()->create(['name' => 'Bad']);

        $this->expectException(InvalidArgumentException::class);
        $this->service->deposit($project, 0, $this->vault);
    }
}
