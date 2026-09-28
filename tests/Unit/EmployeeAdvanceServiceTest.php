<?php

namespace Tests\Unit;

use App\Models\EmployeeAdvance;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\Vault;
use App\Models\Worker;
use App\Services\EmployeeAdvanceService;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class EmployeeAdvanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private EmployeeAdvanceService $service;

    private Vault $vault;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*' => Http::response([
                'rates' => ['IQD' => 1310],
            ], 200),
        ]);

        $this->vault = Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 10000,
            'balance_iqd' => 13_100_000,
        ]);

        $this->service = app(EmployeeAdvanceService::class);
    }

    public function test_create_defaults_remaining_to_amount_and_posts_ledger(): void
    {
        [$worker, $project] = $this->workerAndProject();

        $advance = $this->service->create([
            'worker_id' => $worker->id,
            'project_id' => $project->id,
            'amount_iqd' => 100000,
            'advanced_on' => '2026-09-01',
            'reason' => 'Cash',
            'repayment_method' => EmployeeAdvance::REPAY_PAYROLL,
        ]);

        $this->assertSame('100000.00', (string) $advance->remaining_iqd);
        $this->assertSame(EmployeeAdvance::STATUS_OPEN, $advance->status);

        $this->assertDatabaseHas('transactions', [
            'type' => Transaction::TYPE_ADVANCE,
            'reference_id' => $advance->id,
            'amount_iqd' => 100000,
        ]);

        $this->vault->refresh();
        $this->assertSame(13_000_000.0, (float) $this->vault->balance_iqd);
    }

    public function test_repay_and_open_payroll_remaining(): void
    {
        [$worker, $project] = $this->workerAndProject();

        $advance = $this->service->create([
            'worker_id' => $worker->id,
            'project_id' => $project->id,
            'amount_iqd' => 300000,
            'advanced_on' => '2026-09-01',
            'reason' => 'Cash',
            'repayment_method' => EmployeeAdvance::REPAY_PAYROLL,
        ]);

        $this->service->repay($advance, 100000);
        $this->assertSame(200000.0, $this->service->openPayrollRemainingIqd($worker));

        $this->service->repay($advance->fresh(), 200000);
        $this->assertSame(0.0, $this->service->openPayrollRemainingIqd($worker->fresh()));
        $this->assertSame(EmployeeAdvance::STATUS_REPAID, $advance->fresh()->status);
    }

    public function test_rejects_over_repayment(): void
    {
        [$worker, $project] = $this->workerAndProject();

        $advance = $this->service->create([
            'worker_id' => $worker->id,
            'project_id' => $project->id,
            'amount_iqd' => 10000,
            'advanced_on' => '2026-09-01',
            'reason' => 'Cash',
            'repayment_method' => EmployeeAdvance::REPAY_CASH,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->service->repay($advance, 20000);
    }

    /**
     * @return array{0: Worker, 1: Project}
     */
    private function workerAndProject(): array
    {
        $project = Project::query()->create(['name' => 'Unit Adv Project']);
        $worker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Unit Adv Worker',
        ]);

        return [$worker, $project];
    }
}
