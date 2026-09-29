<?php

namespace App\Services;

use App\Models\ClientAdvance;
use App\Models\ClientRetentionHold;
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
 * Building-client advances to the company (Money In) + 10%/180d retention.
 */
class ClientAdvanceService
{
    public function __construct(
        private readonly VaultBalanceService $balances,
        private readonly RetentionMathService $retention,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{
     *     project_id: int,
     *     client_name: string,
     *     amount: float|int|string,
     *     currency: string,
     *     received_on: string,
     *     reference?: ?string,
     *     notes?: ?string,
     *     vault_id?: ?int,
     *     lock_retention?: bool,
     * }  $data
     */
    public function create(array $data, ?User $actor = null): ClientAdvance
    {
        $project = Project::query()->findOrFail($data['project_id']);
        $vault = isset($data['vault_id'])
            ? Vault::query()->findOrFail($data['vault_id'])
            : $this->zhakoVault();

        $legs = DualCurrency::legs((string) $data['currency'], $data['amount']);
        $lockRetention = (bool) ($data['lock_retention'] ?? true);

        return DB::transaction(function () use ($data, $project, $vault, $legs, $lockRetention, $actor) {
            $txn = Transaction::query()->create([
                'vault_id' => $vault->id,
                'project_id' => $project->id,
                'type' => Transaction::TYPE_MONEY_RECEIVED,
                'direction' => 'in',
                'occurred_on' => $data['received_on'],
                'amount_usd' => $legs['amount_usd'],
                'amount_iqd' => $legs['amount_iqd'],
                'exchange_rate' => 0,
                'description' => sprintf(
                    'Client advance · %s',
                    $data['client_name'],
                ),
                'reference_code' => $data['reference'] ?? null,
                'created_by' => $actor?->id,
            ]);
            $this->balances->apply($txn, $vault);

            $advance = ClientAdvance::query()->create([
                'project_id' => $project->id,
                'vault_id' => $vault->id,
                'client_name' => $data['client_name'],
                'amount_usd' => $legs['amount_usd'],
                'amount_iqd' => $legs['amount_iqd'],
                'currency' => $legs['currency'],
                'received_on' => $data['received_on'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'transaction_id' => $txn->id,
                'entered_by' => $actor?->id,
            ]);

            if ($lockRetention) {
                $held = $this->retention->clientAdvanceRetention(
                    $legs['amount_usd'],
                    $legs['amount_iqd'],
                    $data['received_on'],
                );
                ClientRetentionHold::query()->create([
                    'client_advance_id' => $advance->id,
                    'project_id' => $project->id,
                    'vault_id' => $vault->id,
                    'hold_pct' => $held['hold_pct'],
                    'amount_usd' => $held['retention_usd'],
                    'amount_iqd' => $held['retention_iqd'],
                    'hold_start' => $held['hold_start'],
                    'maturity_date' => $held['maturity_date'],
                    'maturity_days' => $held['maturity_days'],
                    'status' => ClientRetentionHold::STATUS_HOLDING,
                ]);
            }

            $this->audit->log(
                AuditActions::CLIENT_ADVANCE_RECORDED,
                sprintf(
                    'Client advance %.2f %s from %s',
                    DualCurrency::primaryAmount($legs),
                    $legs['currency'],
                    $data['client_name'],
                ),
                $advance,
                [
                    'client_advance_id' => $advance->id,
                    'project_id' => $project->id,
                    'currency' => $legs['currency'],
                    'amount_usd' => $legs['amount_usd'],
                    'amount_iqd' => $legs['amount_iqd'],
                    'transaction_id' => $txn->id,
                ],
                $actor,
            );

            return $advance->fresh(['project', 'retentionHolds', 'transaction']);
        });
    }

    public function softDelete(ClientAdvance $advance, ?User $actor = null): void
    {
        DB::transaction(function () use ($advance, $actor) {
            if ($advance->transaction_id) {
                $txn = Transaction::query()->find($advance->transaction_id);
                if ($txn) {
                    $this->balances->softDeleteAndRebuild($txn);
                }
            }

            foreach ($advance->retentionHolds as $hold) {
                $hold->delete();
            }

            $advance->delete();

            $this->audit->log(
                AuditActions::VAULT_SOFT_DELETE_REBUILD,
                sprintf('Client advance #%d soft-deleted', $advance->id),
                $advance,
                ['client_advance_id' => $advance->id],
                $actor,
            );
        });
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
