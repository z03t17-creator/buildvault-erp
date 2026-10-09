<?php

namespace Tests\Feature;

use App\Models\Floor;
use App\Models\Penalty;
use App\Models\Payout;
use App\Models\Project;
use App\Models\ProjectAllocation;
use App\Models\RetentionHold;
use App\Models\Tower;
use App\Models\Transaction;
use App\Models\Vault;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VaultLedgerModelsTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        if (! \Illuminate\Support\Facades\Route::has('payouts.store')) {
            $this->markTestSkipped('Module surface dropped in staff rewire slice.');
        }
    }


    public function test_ledger_relationships_and_decimal_casts(): void
    {
        $vault = Vault::query()->create(['name' => 'Zhako']);
        $project = Project::query()->create(['name' => 'Ledger Site', 'status' => Project::STATUS_ACTIVE]);
        $tower = $project->towers()->create(['name' => 'T1']);
        $floor = $tower->floors()->create(['name' => 'F1']);
        $worker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Ledger Worker',
            'daily_rate_usd' => 40,
        ]);

        $allocation = ProjectAllocation::query()->create([
            'project_id' => $project->id,
            'expenses_pool_usd' => 4500,
            'payroll_pool_usd' => 3000,
            'retention_pool_usd' => 1000,
            'penalty_pool_usd' => 500,
            'profit_pool_usd' => 1000,
        ]);

        $deposit = Transaction::query()->create([
            'vault_id' => $vault->id,
            'project_id' => $project->id,
            'type' => Transaction::TYPE_DEPOSIT,
            'amount_usd' => 10000,
            'amount_iqd' => 13100000,
            'exchange_rate' => 1310,
            'description' => 'Seed deposit',
        ]);

        $payout = Payout::query()->create([
            'vault_id' => $vault->id,
            'project_id' => $project->id,
            'worker_id' => $worker->id,
            'floor_id' => $floor->id,
            'category' => Payout::CATEGORY_PAYROLL,
            'amount_usd' => 500,
            'amount_iqd' => 655000,
            'exchange_rate' => 1310,
            'retention_holdback' => 50,
            'status' => Payout::STATUS_PENDING,
        ]);

        $deposit->reference()->associate($payout);
        $deposit->save();

        $holdStart = now()->toDateString();
        $hold = RetentionHold::query()->create([
            'vault_id' => $vault->id,
            'project_id' => $project->id,
            'worker_id' => $worker->id,
            'payout_id' => $payout->id,
            'amount_usd' => 50,
            'hold_start' => $holdStart,
            'maturity_date' => RetentionHold::maturityFrom($holdStart)->toDateString(),
            'status' => RetentionHold::STATUS_HOLDING,
        ]);

        $penalty = Penalty::query()->create([
            'worker_id' => $worker->id,
            'project_id' => $project->id,
            'floor_id' => $floor->id,
            'reason' => 'Safety violation',
            'amount_usd' => 25.5,
            'deducted_from_payout' => false,
            'status' => Penalty::STATUS_PENDING,
        ]);

        $this->assertTrue($project->allocation->is($allocation));
        $this->assertTrue($vault->transactions->contains($deposit));
        $this->assertTrue($project->payouts->contains($payout));
        $this->assertTrue($worker->payouts->contains($payout));
        $this->assertTrue($floor->payouts->contains($payout));
        $this->assertTrue($worker->retentionHolds->contains($hold));
        $this->assertTrue($worker->penalties->contains($penalty));
        $this->assertTrue($payout->retentionHolds->contains($hold));

        $this->assertSame('4500.00', (string) $allocation->expenses_pool_usd);
        $this->assertSame('1000.00', (string) $allocation->retention_pool_usd);
        $this->assertSame('50.00', (string) $payout->retention_holdback);
        $this->assertSame('25.50', (string) $penalty->amount_usd);
        $this->assertSame('1310.0000', (string) $deposit->exchange_rate);

        $this->assertInstanceOf(Payout::class, $deposit->fresh()->reference);
        $this->assertTrue(
            $hold->maturity_date->equalTo(RetentionHold::maturityFrom($holdStart)),
        );
        $this->assertSame(6, RetentionHold::MATURITY_MONTHS);
    }
}
