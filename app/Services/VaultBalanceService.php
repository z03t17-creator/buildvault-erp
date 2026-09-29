<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Vault;
use Database\Seeders\VaultSeeder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Qasa-shaped dual-currency vault engine.
 *
 * USD and IQD are independent ledgers on the same row (unused side = 0).
 * Never convert between currencies here — that requires explicit audited FX.
 */
class VaultBalanceService
{
    /**
     * Apply a cash-affecting transaction to vault balances and stamp running
     * balance_after_* on the row. Non-cash types leave vault cash unchanged
     * but still stamp the current vault balances for audit continuity.
     */
    public function apply(Transaction $txn, ?Vault $vault = null): Transaction
    {
        $vault ??= $txn->vault ?? $this->zhakoVault();

        return DB::transaction(function () use ($txn, $vault) {
            $vault = Vault::query()->lockForUpdate()->findOrFail($vault->id);
            $txn = Transaction::query()->lockForUpdate()->findOrFail($txn->id);

            $deltaUsd = $this->signedDeltaUsd($txn);
            $deltaIqd = $this->signedDeltaIqd($txn);

            $vault->balance_usd = round((float) $vault->balance_usd + $deltaUsd, 2);
            $vault->balance_iqd = round((float) $vault->balance_iqd + $deltaIqd, 2);
            $vault->save();

            $txn->balance_after_usd = round((float) $vault->balance_usd, 2);
            $txn->balance_after_iqd = round((float) $vault->balance_iqd, 2);
            $txn->save();

            return $txn->fresh();
        });
    }

    /**
     * Soft-delete a ledger row and rebuild subsequent running balances
     * (and vault totals) per currency with no cross-contamination.
     */
    public function softDeleteAndRebuild(Transaction $txn): void
    {
        DB::transaction(function () use ($txn) {
            $txn = Transaction::query()->lockForUpdate()->findOrFail($txn->id);
            $vaultId = (int) $txn->vault_id;
            $txn->delete(); // SoftDeletes

            $this->rebuildVault($vaultId);
        });
    }

    /**
     * Restore a soft-deleted ledger row and rebuild.
     */
    public function restoreAndRebuild(Transaction $txn): Transaction
    {
        return DB::transaction(function () use ($txn) {
            $txn = Transaction::withTrashed()->lockForUpdate()->findOrFail($txn->id);
            $txn->restore();
            $this->rebuildVault((int) $txn->vault_id);

            return $txn->fresh();
        });
    }

    /**
     * Recalculate all balance_after_* for active cash/non-cash rows and set
     * vault.balance_usd / balance_iqd from the cash ledger only.
     */
    public function rebuildVault(int|Vault $vault): Vault
    {
        $vaultId = $vault instanceof Vault ? (int) $vault->id : $vault;

        return DB::transaction(function () use ($vaultId) {
            $vault = Vault::query()->lockForUpdate()->findOrFail($vaultId);

            $runningUsd = 0.0;
            $runningIqd = 0.0;

            $rows = Transaction::query()
                ->where('vault_id', $vaultId)
                ->orderBy('occurred_on')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($rows as $row) {
                $runningUsd = round($runningUsd + $this->signedDeltaUsd($row), 2);
                $runningIqd = round($runningIqd + $this->signedDeltaIqd($row), 2);

                $dirty = false;
                if ((float) $row->balance_after_usd !== $runningUsd) {
                    $row->balance_after_usd = $runningUsd;
                    $dirty = true;
                }
                if ((float) $row->balance_after_iqd !== $runningIqd) {
                    $row->balance_after_iqd = $runningIqd;
                    $dirty = true;
                }
                if ($dirty) {
                    $row->save();
                }
            }

            $vault->balance_usd = $runningUsd;
            $vault->balance_iqd = $runningIqd;
            $vault->save();

            return $vault->fresh();
        });
    }

    /**
     * Sum signed cash deltas from the active ledger (excludes soft-deleted).
     *
     * @return array{usd: float, iqd: float}
     */
    public function ledgerCashTotals(?Vault $vault = null): array
    {
        $vault ??= $this->zhakoVault();

        $usd = 0.0;
        $iqd = 0.0;

        $rows = Transaction::query()
            ->where('vault_id', $vault->id)
            ->cashAffecting()
            ->get(['type', 'amount_usd', 'amount_iqd']);

        foreach ($rows as $row) {
            $usd = round($usd + $this->signedDeltaUsd($row), 2);
            $iqd = round($iqd + $this->signedDeltaIqd($row), 2);
        }

        return ['usd' => $usd, 'iqd' => $iqd];
    }

    public function signedDeltaUsd(Transaction $txn): float
    {
        $amount = round((float) $txn->amount_usd, 2);

        if ($txn->isCashInflow()) {
            return $amount;
        }

        if ($txn->isCashOutflow()) {
            return -1 * $amount;
        }

        return 0.0;
    }

    public function signedDeltaIqd(Transaction $txn): float
    {
        $amount = round((float) $txn->amount_iqd, 2);

        if ($txn->isCashInflow()) {
            return $amount;
        }

        if ($txn->isCashOutflow()) {
            return -1 * $amount;
        }

        return 0.0;
    }

    /**
     * Normalize a Qasa-shaped amount pair: unused side must be 0 when
     * the entry is single-currency. Both may be non-zero only for
     * explicit dual-leg rows (rare; still never summed).
     *
     * @return array{amount_usd: float, amount_iqd: float}
     */
    public function normalizeDualAmounts(float $amountUsd, float $amountIqd): array
    {
        return [
            'amount_usd' => round(max(0, $amountUsd), 2),
            'amount_iqd' => round(max(0, $amountIqd), 2),
        ];
    }

    protected function zhakoVault(): Vault
    {
        $vault = Vault::query()->where('name', VaultSeeder::NAME)->first()
            ?? Vault::query()->orderBy('id')->first();

        if (! $vault) {
            throw new InvalidArgumentException('No vault found. Seed the Zhako vault first.');
        }

        return $vault;
    }
}
