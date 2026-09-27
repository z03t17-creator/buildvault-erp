<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectAllocation;
use App\Models\Transaction;
use App\Models\Vault;
use App\Support\AuditActions;
use Database\Seeders\VaultSeeder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class VaultService
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRates,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Deposit USD into the Zhako vault, convert via FX, split into project pools,
     * and write ledger entries.
     *
     * Split defaults (overridable per project %): Expenses 45 / Payroll 30 /
     * Insurance(retention) 10 / Penalty 5 / Profit 10 — one shared insurance cut.
     *
     * @return array{
     *     vault: Vault,
     *     project: Project,
     *     allocation: ProjectAllocation,
     *     deposit_transaction: Transaction,
     *     allocation_transaction: Transaction,
     *     rate: float,
     *     amount_usd: float,
     *     amount_iqd: float,
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
    ): array {
        if ($amountUsd <= 0) {
            throw new InvalidArgumentException('Deposit amount must be greater than zero.');
        }

        $amountUsd = round($amountUsd, 2);
        $vault ??= $this->zhakoVault();
        $rate = $this->exchangeRates->getUsdToIqd();
        $amountIqd = round($amountUsd * $rate, 2);

        $percentages = $this->percentagesFor($project);
        $split = $this->splitAmount($amountUsd, $percentages);

        return DB::transaction(function () use (
            $project,
            $vault,
            $amountUsd,
            $amountIqd,
            $rate,
            $split,
            $percentages,
            $createdBy,
            $description,
        ) {
            $vault->refresh();
            $vault->balance_usd = round((float) $vault->balance_usd + $amountUsd, 2);
            $vault->balance_iqd = round((float) $vault->balance_iqd + $amountIqd, 2);
            $vault->save();

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

            $allocation->expenses_pool_usd = round((float) $allocation->expenses_pool_usd + $split['expenses_usd'], 2);
            $allocation->payroll_pool_usd = round((float) $allocation->payroll_pool_usd + $split['payroll_usd'], 2);
            $allocation->retention_pool_usd = round((float) $allocation->retention_pool_usd + $split['retention_usd'], 2);
            $allocation->penalty_pool_usd = round((float) $allocation->penalty_pool_usd + $split['penalty_usd'], 2);
            $allocation->profit_pool_usd = round((float) $allocation->profit_pool_usd + $split['profit_usd'], 2);
            $allocation->save();

            $depositTxn = Transaction::query()->create([
                'vault_id' => $vault->id,
                'project_id' => $project->id,
                'type' => Transaction::TYPE_DEPOSIT,
                'amount_usd' => $amountUsd,
                'amount_iqd' => $amountIqd,
                'exchange_rate' => $rate,
                'description' => $description ?? 'Vault deposit',
                'created_by' => $createdBy,
            ]);

            $allocationTxn = Transaction::query()->create([
                'vault_id' => $vault->id,
                'project_id' => $project->id,
                'type' => Transaction::TYPE_ALLOCATION,
                'amount_usd' => $amountUsd,
                'amount_iqd' => $amountIqd,
                'exchange_rate' => $rate,
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

            $causer = $createdBy ? \App\Models\User::query()->find($createdBy) : null;

            $this->audit->log(
                AuditActions::VAULT_DEPOSIT,
                sprintf('Vault deposit %.2f USD → project #%d', $amountUsd, $project->id),
                $vault,
                [
                    'vault_id' => $vault->id,
                    'project_id' => $project->id,
                    'amount_usd' => $amountUsd,
                    'amount_iqd' => $amountIqd,
                    'exchange_rate' => $rate,
                    'transaction_id' => $depositTxn->id,
                    'split' => $split,
                ],
                $causer,
            );

            $this->audit->log(
                AuditActions::ALLOCATION_CHANGED,
                sprintf('Allocation pools credited from deposit (%.2f USD) on project #%d', $amountUsd, $project->id),
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

            return [
                'vault' => $vault->refresh(),
                'project' => $project,
                'allocation' => $allocation->refresh(),
                'deposit_transaction' => $depositTxn,
                'allocation_transaction' => $allocationTxn,
                'rate' => $rate,
                'amount_usd' => $amountUsd,
                'amount_iqd' => $amountIqd,
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
                    'note' => 'Full ability-to-pay / liquidity validation lands in Phase 3.3 (LiquidityService).',
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
