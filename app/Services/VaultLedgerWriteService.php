<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vault;
use App\Support\AuditActions;
use App\Support\DualCurrency;
use Database\Seeders\VaultSeeder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Phase 2 — Vault ledger Money In / Money Out CRUD (Qasa dual columns).
 */
class VaultLedgerWriteService
{
    public function __construct(
        private readonly VaultBalanceService $balances,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{
     *     direction: 'in'|'out',
     *     currency: string,
     *     amount: float|int|string,
     *     occurred_on: string,
     *     description: string,
     *     project_id?: int|null,
     *     type?: string|null,
     *     reference_code?: string|null,
     *     vault_id?: int|null,
     * }  $data
     */
    public function create(array $data, ?User $actor = null): Transaction
    {
        $direction = (string) ($data['direction'] ?? '');
        if (! in_array($direction, ['in', 'out'], true)) {
            throw new InvalidArgumentException('Direction must be Money In or Money Out.');
        }

        $legs = DualCurrency::legs((string) $data['currency'], $data['amount']);
        $type = $data['type'] ?? ($direction === 'in' ? Transaction::TYPE_DEPOSIT : Transaction::TYPE_WITHDRAWAL);

        if ($direction === 'in' && ! in_array($type, Transaction::CASH_INFLOW_TYPES, true)) {
            $type = Transaction::TYPE_DEPOSIT;
        }
        if ($direction === 'out' && ! in_array($type, Transaction::CASH_OUTFLOW_TYPES, true)) {
            $type = Transaction::TYPE_WITHDRAWAL;
        }

        $vault = isset($data['vault_id'])
            ? Vault::query()->findOrFail($data['vault_id'])
            : $this->zhakoVault();

        return DB::transaction(function () use ($data, $legs, $type, $direction, $vault, $actor) {
            if ($direction === 'out') {
                $this->assertCashAvailable($vault, $legs);
            }

            $txn = Transaction::query()->create([
                'vault_id' => $vault->id,
                'project_id' => $data['project_id'] ?? null,
                'type' => $type,
                'direction' => $direction,
                'occurred_on' => $data['occurred_on'],
                'amount_usd' => $legs['amount_usd'],
                'amount_iqd' => $legs['amount_iqd'],
                'exchange_rate' => $legs['exchange_rate'],
                'description' => $data['description'],
                'reference_code' => $data['reference_code'] ?? null,
                'created_by' => $actor?->id,
            ]);

            $this->balances->apply($txn, $vault);

            $this->audit->log(
                $direction === 'in' ? AuditActions::VAULT_MONEY_IN : AuditActions::VAULT_MONEY_OUT,
                sprintf(
                    '%s %.2f %s',
                    $direction === 'in' ? 'Money In' : 'Money Out',
                    DualCurrency::primaryAmount($legs),
                    $legs['currency'],
                ),
                $txn,
                [
                    'transaction_id' => $txn->id,
                    'direction' => $direction,
                    'currency' => $legs['currency'],
                    'amount_usd' => $legs['amount_usd'],
                    'amount_iqd' => $legs['amount_iqd'],
                ],
                $actor,
            );

            return $txn->fresh(['project', 'creator']);
        });
    }

    /**
     * @param  array{
     *     direction?: 'in'|'out',
     *     currency?: string,
     *     amount?: float|int|string,
     *     occurred_on?: string,
     *     description?: string,
     *     project_id?: int|null,
     *     type?: string|null,
     *     reference_code?: string|null,
     * }  $data
     */
    public function update(Transaction $txn, array $data, ?User $actor = null): Transaction
    {
        return DB::transaction(function () use ($txn, $data, $actor) {
            $txn = Transaction::query()->lockForUpdate()->findOrFail($txn->id);
            $vault = Vault::query()->lockForUpdate()->findOrFail($txn->vault_id);

            $direction = (string) ($data['direction'] ?? $txn->direction ?? ($txn->isCashOutflow() ? 'out' : 'in'));
            $currency = (string) ($data['currency'] ?? (
                (float) $txn->amount_usd > 0 ? DualCurrency::USD : DualCurrency::IQD
            ));
            $amount = $data['amount'] ?? DualCurrency::primaryAmount([
                'currency' => $currency,
                'amount_usd' => (float) $txn->amount_usd,
                'amount_iqd' => (float) $txn->amount_iqd,
            ]);

            $legs = DualCurrency::legs($currency, $amount);
            $type = $data['type'] ?? $txn->type;
            if ($direction === 'in' && ! in_array($type, Transaction::CASH_INFLOW_TYPES, true)) {
                $type = Transaction::TYPE_DEPOSIT;
            }
            if ($direction === 'out' && ! in_array($type, Transaction::CASH_OUTFLOW_TYPES, true)) {
                $type = Transaction::TYPE_WITHDRAWAL;
            }

            // Soft-remove old effect, write new amounts, rebuild.
            $txn->fill([
                'type' => $type,
                'direction' => $direction,
                'occurred_on' => $data['occurred_on'] ?? $txn->occurred_on,
                'amount_usd' => $legs['amount_usd'],
                'amount_iqd' => $legs['amount_iqd'],
                'exchange_rate' => $legs['exchange_rate'],
                'description' => $data['description'] ?? $txn->description,
                'project_id' => array_key_exists('project_id', $data) ? $data['project_id'] : $txn->project_id,
                'reference_code' => array_key_exists('reference_code', $data) ? $data['reference_code'] : $txn->reference_code,
            ]);
            $txn->save();

            $this->balances->rebuildVault($vault);

            if ($direction === 'out') {
                $vault->refresh();
                $this->assertCashAvailable($vault, $legs, allowZeroAfter: true);
            }

            $this->audit->log(
                AuditActions::VAULT_LEDGER_UPDATED,
                sprintf('Vault ledger #%d updated', $txn->id),
                $txn,
                [
                    'transaction_id' => $txn->id,
                    'direction' => $direction,
                    'currency' => $legs['currency'],
                    'amount_usd' => $legs['amount_usd'],
                    'amount_iqd' => $legs['amount_iqd'],
                ],
                $actor,
            );

            return $txn->fresh(['project', 'creator']);
        });
    }

    public function softDelete(Transaction $txn, ?User $actor = null): void
    {
        DB::transaction(function () use ($txn, $actor) {
            $txn = Transaction::query()->lockForUpdate()->findOrFail($txn->id);
            $this->balances->softDeleteAndRebuild($txn);

            $this->audit->log(
                AuditActions::VAULT_SOFT_DELETE_REBUILD,
                sprintf('Vault ledger #%d soft-deleted; balances rebuilt', $txn->id),
                $txn,
                ['transaction_id' => $txn->id],
                $actor,
            );
        });
    }

    /**
     * @param  array{currency: string, amount_usd: float, amount_iqd: float}  $legs
     */
    protected function assertCashAvailable(Vault $vault, array $legs, bool $allowZeroAfter = false): void
    {
        if ($legs['currency'] === DualCurrency::USD) {
            if ((float) $vault->balance_usd + 0.0001 < $legs['amount_usd'] && ! $allowZeroAfter) {
                throw new InvalidArgumentException('Cannot post Money Out: Available Cash USD is insufficient.');
            }
            if ($allowZeroAfter && (float) $vault->balance_usd < -0.0001) {
                throw new InvalidArgumentException('Cannot post Money Out: Available Cash USD is insufficient.');
            }
        } else {
            if ((float) $vault->balance_iqd + 0.0001 < $legs['amount_iqd'] && ! $allowZeroAfter) {
                throw new InvalidArgumentException('Cannot post Money Out: Available Cash IQD is insufficient.');
            }
            if ($allowZeroAfter && (float) $vault->balance_iqd < -0.0001) {
                throw new InvalidArgumentException('Cannot post Money Out: Available Cash IQD is insufficient.');
            }
        }
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
