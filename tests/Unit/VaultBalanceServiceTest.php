<?php

namespace Tests\Unit;

use App\Models\Transaction;
use App\Models\Vault;
use App\Services\VaultBalanceService;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VaultBalanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private VaultBalanceService $service;

    private Vault $vault;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vault = Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 0,
            'balance_iqd' => 0,
        ]);

        $this->service = app(VaultBalanceService::class);
    }

    public function test_usd_and_iqd_deposits_do_not_cross_contaminate(): void
    {
        $usd = Transaction::query()->create([
            'vault_id' => $this->vault->id,
            'type' => Transaction::TYPE_DEPOSIT,
            'occurred_on' => '2026-09-01',
            'amount_usd' => 1000,
            'amount_iqd' => 0,
            'exchange_rate' => 0,
            'description' => 'USD only',
        ]);
        $this->service->apply($usd, $this->vault);

        $iqd = Transaction::query()->create([
            'vault_id' => $this->vault->id,
            'type' => Transaction::TYPE_DEPOSIT,
            'occurred_on' => '2026-09-02',
            'amount_usd' => 0,
            'amount_iqd' => 500000,
            'exchange_rate' => 0,
            'description' => 'IQD only',
        ]);
        $this->service->apply($iqd, $this->vault);

        $this->vault->refresh();
        $this->assertSame(1000.0, (float) $this->vault->balance_usd);
        $this->assertSame(500000.0, (float) $this->vault->balance_iqd);

        $usd->refresh();
        $iqd->refresh();
        $this->assertSame(1000.0, (float) $usd->balance_after_usd);
        $this->assertSame(0.0, (float) $usd->balance_after_iqd);
        $this->assertSame(1000.0, (float) $iqd->balance_after_usd);
        $this->assertSame(500000.0, (float) $iqd->balance_after_iqd);
    }

    public function test_soft_delete_rebuilds_subsequent_balances_per_currency(): void
    {
        $a = Transaction::query()->create([
            'vault_id' => $this->vault->id,
            'type' => Transaction::TYPE_DEPOSIT,
            'occurred_on' => '2026-09-01',
            'amount_usd' => 200,
            'amount_iqd' => 0,
            'exchange_rate' => 0,
        ]);
        $this->service->apply($a, $this->vault);

        $b = Transaction::query()->create([
            'vault_id' => $this->vault->id,
            'type' => Transaction::TYPE_DEPOSIT,
            'occurred_on' => '2026-09-02',
            'amount_usd' => 0,
            'amount_iqd' => 100000,
            'exchange_rate' => 0,
        ]);
        $this->service->apply($b, $this->vault);

        $c = Transaction::query()->create([
            'vault_id' => $this->vault->id,
            'type' => Transaction::TYPE_EXPENSE,
            'occurred_on' => '2026-09-03',
            'amount_usd' => 50,
            'amount_iqd' => 0,
            'exchange_rate' => 0,
        ]);
        $this->service->apply($c, $this->vault);

        $this->vault->refresh();
        $this->assertSame(150.0, (float) $this->vault->balance_usd);
        $this->assertSame(100000.0, (float) $this->vault->balance_iqd);

        // Soft-delete the USD deposit — IQD must stay untouched.
        $this->service->softDeleteAndRebuild($a);

        $this->vault->refresh();
        $this->assertSame(-50.0, (float) $this->vault->balance_usd);
        $this->assertSame(100000.0, (float) $this->vault->balance_iqd);

        $this->assertSoftDeleted('transactions', ['id' => $a->id]);

        $b->refresh();
        $c->refresh();
        $this->assertSame(0.0, (float) $b->balance_after_usd);
        $this->assertSame(100000.0, (float) $b->balance_after_iqd);
        $this->assertSame(-50.0, (float) $c->balance_after_usd);
        $this->assertSame(100000.0, (float) $c->balance_after_iqd);
    }

    public function test_rebuild_matches_ledger_cash_totals(): void
    {
        Transaction::query()->create([
            'vault_id' => $this->vault->id,
            'type' => Transaction::TYPE_DEPOSIT,
            'occurred_on' => '2026-09-01',
            'amount_usd' => 10,
            'amount_iqd' => 20000,
            'exchange_rate' => 0,
        ]);
        Transaction::query()->create([
            'vault_id' => $this->vault->id,
            'type' => Transaction::TYPE_WITHDRAWAL,
            'occurred_on' => '2026-09-02',
            'amount_usd' => 3,
            'amount_iqd' => 5000,
            'exchange_rate' => 0,
        ]);

        $vault = $this->service->rebuildVault($this->vault);

        $this->assertSame(7.0, (float) $vault->balance_usd);
        $this->assertSame(15000.0, (float) $vault->balance_iqd);

        $totals = $this->service->ledgerCashTotals($vault);
        $this->assertSame(7.0, $totals['usd']);
        $this->assertSame(15000.0, $totals['iqd']);
    }

    public function test_non_cash_types_do_not_move_vault_cash(): void
    {
        $deposit = Transaction::query()->create([
            'vault_id' => $this->vault->id,
            'type' => Transaction::TYPE_DEPOSIT,
            'occurred_on' => '2026-09-01',
            'amount_usd' => 100,
            'amount_iqd' => 0,
            'exchange_rate' => 0,
        ]);
        $this->service->apply($deposit, $this->vault);

        $alloc = Transaction::query()->create([
            'vault_id' => $this->vault->id,
            'type' => Transaction::TYPE_ALLOCATION,
            'occurred_on' => '2026-09-01',
            'amount_usd' => 45,
            'amount_iqd' => 0,
            'exchange_rate' => 0,
        ]);
        $this->service->apply($alloc, $this->vault);

        $this->vault->refresh();
        $this->assertSame(100.0, (float) $this->vault->balance_usd);
        $this->assertSame(0.0, (float) $this->vault->balance_iqd);
    }
}
