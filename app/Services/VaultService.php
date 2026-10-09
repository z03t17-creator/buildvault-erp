<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectAllocation;
use App\Models\Transaction;
use App\Models\Vault;
use App\Support\AuditActions;
use App\Support\DualCurrency;
use Database\Seeders\VaultSeeder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class VaultService
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRates,
        private readonly AuditLogger $audit,
        private readonly VaultBalanceService $balances,
    ) {}

    /**
     * Deposit into the Zhako vault (Qasa single-leg). Default currency USD.
     * Unused currency column stays 0 — never FX-invented.
     *
     * Split defaults (USD only, overridable per project %): Expenses 45 / Payroll 30 /
     * Insurance(retention) 10 / Penalty 5 / Profit 10.
     *
     * @return array{
     *     vault: Vault,
     *     project: Project,
     *     allocation: ProjectAllocation,
     *     deposit_transaction: Transaction,
     *     allocation_transaction: ?Transaction,
     *     rate: float,
     *     amount_usd: float,
     *     amount_iqd: float,
     *     currency: string,
     *     split: array{
     *         expenses_usd: float,
     *         payroll_usd: float,
     *         retention_usd: float,
     *         penalty_usd: float,
     *         profit_usd: float,
     *     },
     *     percentages: array{
     *         expenses: float,
     *         payroll: float,
     *         retention: float,
     *         penalty: float,
     *         profit: float,
     *     },
     *     ability: array{
     *         vault_balance_usd: float,
     *         vault_balance_iqd: float,
     *         pools: array<string, float>,
     *         note: string,
     *     }
     * }
     */
    public function deposit(
        Project $project,
        float $amountUsd,
        ?Vault $vault = null,
        ?int $createdBy = null,
        ?string $description = null,
        string $ledgerType = Transaction::TYPE_DEPOSIT,
        bool $allocatePools = true,
        ?string $occurredOn = null,
        ?string $referenceCode = null,
        string $currency = DualCurrency::USD,
        float|int|string|null $amount = null,
    ): array {
        $legs = DualCurrency::legs(
            $currency,
            $amount !== null ? $amount : $amountUsd,
        );

        if (! in_array($ledgerType, Transaction::CASH_INFLOW_TYPES, true)) {
            throw new InvalidArgumentException("Ledger type [{$ledgerType}] is not a cash inflow.");
        }

        $vault ??= $this->zhakoVault();
        $occurredOn ??= now()->toDateString();
        $percentages = $this->percentagesFor($project);

        // Pool allocation is USD-denominated; IQD deposits credit cash only.
        $allocatePools = $allocatePools && $legs['currency'] === DualCurrency::USD;
        $split = $allocatePools
            ? $this->splitAmount($legs['amount_usd'], $percentages)
            : [
                'expenses_usd' => 0.0,
                'payroll_usd' => 0.0,
                'retention_usd' => 0.0,
                'penalty_usd' => 0.0,
                'profit_usd' => 0.0,
            ];

        return DB::transaction(function () use (
            $project,
            $vault,
            $legs,
            $split,
            $percentages,
            $createdBy,
            $description,
            $ledgerType,
            $allocatePools,
            $occurredOn,
            $referenceCode,
        ) {
            $allocation = ProjectAllocation::query()->firstOrCreate(
                ['project_id' => $project->id],
                [
                    'expenses_pool_usd' => 0,
                    'payroll_pool_usd' => 0,
                    'retention_pool_usd' => 0,
                    'penalty_pool_usd' => 0,
                    'profit_pool_usd' => 0,
                ],
            );

            $poolsBefore = [
                'expenses_pool_usd' => (float) $allocation->expenses_pool_usd,
                'payroll_pool_usd' => (float) $allocation->payroll_pool_usd,
                'retention_pool_usd' => (float) $allocation->retention_pool_usd,
                'penalty_pool_usd' => (float) $allocation->penalty_pool_usd,
                'profit_pool_usd' => (float) $allocation->profit_pool_usd,
            ];

            $allocationTxn = null;
            if ($allocatePools) {
                $allocation->expenses_pool_usd = round((float) $allocation->expenses_pool_usd + $split['expenses_usd'], 2);
                $allocation->payroll_pool_usd = round((float) $allocation->payroll_pool_usd + $split['payroll_usd'], 2);
                $allocation->retention_pool_usd = round((float) $allocation->retention_pool_usd + $split['retention_usd'], 2);
                $allocation->penalty_pool_usd = round((float) $allocation->penalty_pool_usd + $split['penalty_usd'], 2);
                $allocation->profit_pool_usd = round((float) $allocation->profit_pool_usd + $split['profit_usd'], 2);
                $allocation->save();
            }

            $depositTxn = Transaction::query()->create([
                'vault_id' => $vault->id,
                'project_id' => $project->id,
                'type' => $ledgerType,
                'direction' => 'in',
                'occurred_on' => $occurredOn,
                'amount_usd' => $legs['amount_usd'],
                'amount_iqd' => $legs['amount_iqd'],
                'exchange_rate' => $legs['exchange_rate'],
                'description' => $description ?? 'Money In',
                'reference_code' => $referenceCode,
                'created_by' => $createdBy,
            ]);

            $this->balances->apply($depositTxn, $vault);

            if ($allocatePools) {
                $allocationTxn = Transaction::query()->create([
                    'vault_id' => $vault->id,
                    'project_id' => $project->id,
                    'type' => Transaction::TYPE_ALLOCATION,
                    'occurred_on' => $occurredOn,
                    'amount_usd' => $legs['amount_usd'],
                    'amount_iqd' => 0,
                    'exchange_rate' => 0,
                    'description' => sprintf(
                        'Pool split expenses %.2f / payroll %.2f / retention %.2f / penalty %.2f / profit %.2f',
                        $split['expenses_usd'],
                        $split['payroll_usd'],
                        $split['retention_usd'],
                        $split['penalty_usd'],
                        $split['profit_usd'],
                    ),
                    'reference_type' => $depositTxn->getMorphClass(),
                    'reference_id' => $depositTxn->id,
                    'created_by' => $createdBy,
                ]);
                $this->balances->apply($allocationTxn, $vault);
            }

            $vault->refresh();
            $causer = $createdBy ? \App\Models\User::query()->find($createdBy) : null;

            $this->audit->log(
                AuditActions::VAULT_DEPOSIT,
                sprintf(
                    'Money In %.2f %s → project #%d',
                    DualCurrency::primaryAmount($legs),
                    $legs['currency'],
                    $project->id,
                ),
                $vault,
                [
                    'vault_id' => $vault->id,
                    'project_id' => $project->id,
                    'currency' => $legs['currency'],
                    'amount_usd' => $legs['amount_usd'],
                    'amount_iqd' => $legs['amount_iqd'],
                    'exchange_rate' => $legs['exchange_rate'],
                    'transaction_id' => $depositTxn->id,
                    'split' => $split,
                ],
                $causer,
            );

            if ($allocatePools) {
                $this->audit->log(
                    AuditActions::ALLOCATION_CHANGED,
                    sprintf('Allocation pools credited from Money In (%.2f USD) on project #%d', $legs['amount_usd'], $project->id),
                    $allocation,
                    [
                        'project_id' => $project->id,
                        'reason' => 'vault_deposit',
                        'before' => $poolsBefore,
                        'after' => [
                            'expenses_pool_usd' => (float) $allocation->expenses_pool_usd,
                            'payroll_pool_usd' => (float) $allocation->payroll_pool_usd,
                            'retention_pool_usd' => (float) $allocation->retention_pool_usd,
                            'penalty_pool_usd' => (float) $allocation->penalty_pool_usd,
                            'profit_pool_usd' => (float) $allocation->profit_pool_usd,
                        ],
                        'split' => $split,
                        'deposit_transaction_id' => $depositTxn->id,
                    ],
                    $causer,
                );
            }

            return [
                'vault' => $vault->refresh(),
                'project' => $project,
                'allocation' => $allocation->refresh(),
                'deposit_transaction' => $depositTxn->fresh(),
                'allocation_transaction' => $allocationTxn?->fresh(),
                'rate' => $legs['exchange_rate'],
                'amount_usd' => $legs['amount_usd'],
                'amount_iqd' => $legs['amount_iqd'],
                'currency' => $legs['currency'],
                'split' => $split,
                'percentages' => $percentages,
                'ability' => [
                    'vault_balance_usd' => (float) $vault->balance_usd,
                    'vault_balance_iqd' => (float) $vault->balance_iqd,
                    'pools' => [
                        'expenses_usd' => (float) $allocation->expenses_pool_usd,
                        'payroll_usd' => (float) $allocation->payroll_pool_usd,
                        'retention_usd' => (float) $allocation->retention_pool_usd,
                        'penalty_usd' => (float) $allocation->penalty_pool_usd,
                        'profit_usd' => (float) $allocation->profit_pool_usd,
                    ],
                    'note' => $allocatePools
                        ? 'Pool allocation applied (optional helper). Liquidity via LiquidityService.'
                        : 'Money In posted without pool allocation (Qasa single-leg).',
                ],
            ];
        });
    }

    /**
     * @return array{expenses: float, payroll: float, retention: float, penalty: float, profit: float}
     */
    public function percentagesFor(Project $project): array
    {
        return [
            'expenses' => (float) $project->allocation_expenses_pct,
            'payroll' => (float) $project->allocation_payroll_pct,
            'retention' => (float) $project->allocation_insurance_pct,
            'penalty' => (float) $project->allocation_penalty_pct,
            'profit' => (float) $project->allocation_profit_pct,
        ];
    }

    /**
     * Split USD across five pools; remainder cents go to profit so totals equal deposit.
     *
     * @param  array{expenses: float, payroll: float, retention: float, penalty: float, profit: float}  $percentages
     * @return array{expenses_usd: float, payroll_usd: float, retention_usd: float, penalty_usd: float, profit_usd: float}
     */
    public function splitAmount(float $amountUsd, array $percentages): array
    {
        $amountUsd = round($amountUsd, 2);

        $expenses = round($amountUsd * ($percentages['expenses'] / 100), 2);
        $payroll = round($amountUsd * ($percentages['payroll'] / 100), 2);
        $retention = round($amountUsd * ($percentages['retention'] / 100), 2);
        $penalty = round($amountUsd * ($percentages['penalty'] / 100), 2);
        $profit = round($amountUsd - $expenses - $payroll - $retention - $penalty, 2);

        return [
            'expenses_usd' => $expenses,
            'payroll_usd' => $payroll,
            'retention_usd' => $retention,
            'penalty_usd' => $penalty,
            'profit_usd' => $profit,
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
