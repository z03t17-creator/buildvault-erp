<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\ProjectAllocation;
use App\Models\RetentionHold;
use App\Models\Transaction;
use App\Models\Vault;
use App\Models\Worker;
use App\Services\ExchangeRateService;
use App\Services\LiquidityService;
use App\Services\RetentionHoldService;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class RetentionHoldServiceTest extends TestCase
{
    use RefreshDatabase;

    private RetentionHoldService $service;

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

        $this->project = Project::query()->create(['name' => 'Insurance Site']);
        ProjectAllocation::query()->create([
            'project_id' => $this->project->id,
            'expenses_pool_usd' => 4500,
            'payroll_pool_usd' => 2000,
            'retention_pool_usd' => 1000,
            'penalty_pool_usd' => 500,
            'profit_pool_usd' => 1000,
        ]);

        $this->worker = Worker::query()->create([
            'project_id' => $this->project->id,
            'name' => 'Hold Worker',
        ]);

        $this->service = app(RetentionHoldService::class);
    }

    public function test_maturity_from_adds_six_months(): void
    {
        $maturity = RetentionHold::maturityFrom('2026-01-15');

        $this->assertSame('2026-07-15', $maturity->toDateString());
    }

    public function test_mark_due_as_matured_flips_holding_when_date_reached(): void
    {
        $due = RetentionHold::query()->create([
            'vault_id' => $this->vault->id,
            'project_id' => $this->project->id,
            'worker_id' => $this->worker->id,
            'amount_usd' => 100,
            'hold_start' => now()->subMonths(6)->toDateString(),
            'maturity_date' => now()->toDateString(),
            'status' => RetentionHold::STATUS_HOLDING,
        ]);

        $future = RetentionHold::query()->create([
            'vault_id' => $this->vault->id,
            'project_id' => $this->project->id,
            'worker_id' => $this->worker->id,
            'amount_usd' => 50,
            'hold_start' => now()->toDateString(),
            'maturity_date' => now()->addMonths(6)->toDateString(),
            'status' => RetentionHold::STATUS_HOLDING,
        ]);

        $matured = $this->service->markDueAsMatured();

        $this->assertCount(1, $matured);
        $this->assertSame(RetentionHold::STATUS_MATURED, $due->fresh()->status);
        $this->assertSame(RetentionHold::STATUS_HOLDING, $future->fresh()->status);
    }

    public function test_artisan_command_marks_due_holds(): void
    {
        RetentionHold::query()->create([
            'vault_id' => $this->vault->id,
            'project_id' => $this->project->id,
            'worker_id' => $this->worker->id,
            'amount_usd' => 80,
            'hold_start' => now()->subMonths(7)->toDateString(),
            'maturity_date' => now()->subDay()->toDateString(),
            'status' => RetentionHold::STATUS_HOLDING,
        ]);

        $exit = Artisan::call('retention:check-maturity');

        $this->assertSame(0, $exit);
        $this->assertDatabaseHas('retention_holds', [
            'amount_usd' => 80,
            'status' => RetentionHold::STATUS_MATURED,
        ]);
    }

    public function test_release_credits_payroll_pool_and_ledger(): void
    {
        $hold = RetentionHold::query()->create([
            'vault_id' => $this->vault->id,
            'project_id' => $this->project->id,
            'worker_id' => $this->worker->id,
            'amount_usd' => 150,
            'hold_start' => now()->subMonths(7)->toDateString(),
            'maturity_date' => now()->subMonth()->toDateString(),
            'status' => RetentionHold::STATUS_MATURED,
        ]);

        $released = $this->service->release($hold);

        $this->assertSame(RetentionHold::STATUS_RELEASED, $released->status);
        $this->assertNotNull($released->released_at);
        $this->assertSame('150.00', (string) $released->released_amount_usd);

        $allocation = ProjectAllocation::query()->where('project_id', $this->project->id)->first();
        // 2000 + 150
        $this->assertSame('2150.00', (string) $allocation->payroll_pool_usd);

        $this->assertDatabaseHas('transactions', [
            'type' => Transaction::TYPE_ADJUSTMENT,
            'reference_id' => $hold->id,
            'amount_usd' => 150,
        ]);

        $this->assertSame(0.0, app(LiquidityService::class)->reservedInsuranceUsd($this->vault));
    }

    public function test_cannot_release_holding_before_maturity_status(): void
    {
        $hold = RetentionHold::query()->create([
            'vault_id' => $this->vault->id,
            'project_id' => $this->project->id,
            'worker_id' => $this->worker->id,
            'amount_usd' => 40,
            'hold_start' => now()->toDateString(),
            'maturity_date' => now()->addMonths(6)->toDateString(),
            'status' => RetentionHold::STATUS_HOLDING,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->service->release($hold);
    }

    public function test_matured_awaiting_release_lists_alerts(): void
    {
        RetentionHold::query()->create([
            'vault_id' => $this->vault->id,
            'project_id' => $this->project->id,
            'worker_id' => $this->worker->id,
            'amount_usd' => 25,
            'hold_start' => now()->subMonths(7)->toDateString(),
            'maturity_date' => now()->subDays(3)->toDateString(),
            'status' => RetentionHold::STATUS_MATURED,
        ]);

        RetentionHold::query()->create([
            'vault_id' => $this->vault->id,
            'project_id' => $this->project->id,
            'worker_id' => $this->worker->id,
            'amount_usd' => 10,
            'hold_start' => now()->toDateString(),
            'maturity_date' => now()->addMonths(6)->toDateString(),
            'status' => RetentionHold::STATUS_HOLDING,
        ]);

        $this->assertSame(1, $this->service->maturedCount());
        $this->assertCount(1, $this->service->maturedAwaitingRelease());
    }
}
