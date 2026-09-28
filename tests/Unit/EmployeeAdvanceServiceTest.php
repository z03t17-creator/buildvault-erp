<?php

namespace Tests\Unit;

use App\Models\EmployeeAdvance;
use App\Models\Project;
use App\Models\Worker;
use App\Services\EmployeeAdvanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class EmployeeAdvanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private EmployeeAdvanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EmployeeAdvanceService;
    }

    public function test_create_defaults_remaining_to_amount(): void
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
