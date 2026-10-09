<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Payout;
use App\Models\Project;
use App\Models\ProjectReceipt;
use App\Models\Transaction;
use App\Models\Vault;
use App\Support\AuditActions;
use App\Support\DualCurrency;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Project-level IQD financial summary and money-received recording.
 *
 * material_cost_iqd = real DB rollup of stock-OUT movements for the project
 * (quantity × purchase_price_iqd snapshot at issue). Stock IN never adds to
 * this figure and does not create Expense/vault rows — site cash spend stays
 * on the Expenses module so costs are not double-counted.
 */
class ProjectFinancialService
{
    public function __construct(
        private readonly VaultService $vault,
        private readonly ExchangeRateService $exchangeRates,
        private readonly AuditLogger $audit,
        private readonly StockService $stock,
    ) {}

    /**
     * @return array{
     *     contract_value_iqd: float,
     *     money_received_iqd: float,
     *     project_expenses_iqd: float,
     *     material_cost_iqd: float,
     *     payroll_cost_iqd: float,
     *     other_expenses_iqd: float,
     *     remaining_vs_contract_iqd: float,
     *     net_position_iqd: float,
     *     currency: string,
     *     stubs: array<string, string>,
     * }
     */
    public function summary(Project $project): array
    {
        $contractValue = round((float) $project->contract_value_iqd, 2);
        $moneyReceived = $this->moneyReceivedIqd($project);
        $projectExpenses = $this->projectExpensesIqd($project);
        $materialCost = $this->materialCostIqd($project);
        $payrollCost = $this->payrollCostIqd($project);
        $otherExpenses = $this->otherExpensesIqd($project);

        $remainingVsContract = round($contractValue - $moneyReceived, 2);
        $netPosition = round(
            $moneyReceived - ($projectExpenses + $materialCost + $payrollCost + $otherExpenses),
            2,
        );

        return [
            'contract_value_iqd' => $contractValue,
            'money_received_iqd' => $moneyReceived,
            'project_expenses_iqd' => $projectExpenses,
            'material_cost_iqd' => $materialCost,
            'payroll_cost_iqd' => $payrollCost,
            'other_expenses_iqd' => $otherExpenses,
            'remaining_vs_contract_iqd' => $remainingVsContract,
            'net_position_iqd' => $netPosition,
            'currency' => 'IQD',
            'stubs' => [],
        ];
    }

    /**
     * Stock-out purchase value attributed to the project (qty × unit price).
     */
    public function materialCostIqd(Project $project): float
    {
        return $this->stock->materialCostForProject((int) $project->id);
    }

    /**
     * Recent materials issued from stock to this project.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function recentMaterialsUsed(Project $project, int $limit = 12)
    {
        return $this->stock->recentMaterialsForProject((int) $project->id, $limit);
    }

    /**
     * Approved project expenses (Expense module — not vault-pool payout labels).
     * Legacy Payout category=expenses are excluded here to avoid double-counting.
     */
    public function projectExpensesIqd(Project $project): float
    {
        $total = (float) Expense::query()
            ->where('project_id', $project->id)
            ->where('approval_status', Expense::STATUS_APPROVED)
            ->sum('amount_iqd');

        return round($total, 2);
    }

    /**
     * Money received = sum of vault deposit ledger IQD for this project
     * (receipts call VaultService::deposit; legacy deposits also count).
     */
    public function moneyReceivedIqd(Project $project): float
    {
        $total = (float) Transaction::query()
            ->where('project_id', $project->id)
            ->whereIn('type', Transaction::MONEY_RECEIVED_TYPES)
            ->sum('amount_iqd');

        return round($total, 2);
    }

    /**
     * Payroll cost from approved or reconciled payroll payouts on the project.
     */
    public function payrollCostIqd(Project $project): float
    {
        $total = (float) Payout::query()
            ->where('project_id', $project->id)
            ->where('category', Payout::CATEGORY_PAYROLL)
            ->whereIn('status', [Payout::STATUS_APPROVED, Payout::STATUS_RECONCILED])
            ->sum('amount_iqd');

        return round($total, 2);
    }

    /**
     * Other expenses: approved/reconciled non-payroll, non-expenses payouts
     * (penalty / retention / profit). Expenses category payouts are ignored —
     * project site spend lives on the Expense module.
     */
    public function otherExpensesIqd(Project $project): float
    {
        $total = (float) Payout::query()
            ->where('project_id', $project->id)
            ->whereNotIn('category', [Payout::CATEGORY_PAYROLL, Payout::CATEGORY_EXPENSES])
            ->whereIn('status', [Payout::STATUS_APPROVED, Payout::STATUS_RECONCILED])
            ->sum('amount_iqd');

        return round($total, 2);
    }

    /**
     * Record client money received (Qasa single-leg). Default IQD; unused side = 0.
     *
     * @param  array{
     *     amount_iqd?: float|int|string,
     *     amount_usd?: float|int|string,
     *     amount?: float|int|string,
     *     currency?: string,
     *     received_on: string,
     *     source?: ?string,
     *     reference?: ?string,
     *     notes?: ?string,
     *     entered_by?: ?int,
     *     vault?: ?Vault,
     * }  $data
     * @return array{receipt: ProjectReceipt, deposit: array<string, mixed>, summary: array<string, mixed>}
     */
    public function recordReceipt(Project $project, array $data): array
    {
        $currency = strtoupper((string) ($data['currency'] ?? DualCurrency::IQD));
        $amount = $data['amount']
            ?? ($currency === DualCurrency::USD ? ($data['amount_usd'] ?? null) : ($data['amount_iqd'] ?? null));
        $legs = DualCurrency::legs($currency, $amount);

        $enteredBy = $data['entered_by'] ?? null;
        $description = trim(sprintf(
            'Project receipt%s%s',
            ! empty($data['source']) ? ' · '.$data['source'] : '',
            ! empty($data['reference']) ? ' · ref '.$data['reference'] : '',
        ));

        return DB::transaction(function () use (
            $project,
            $data,
            $legs,
            $enteredBy,
            $description,
        ) {
            $deposit = $this->vault->deposit(
                $project,
                $legs['amount_usd'] > 0 ? $legs['amount_usd'] : $legs['amount_iqd'],
                $data['vault'] ?? null,
                $enteredBy,
                $description !== 'Project receipt' ? $description : 'Project money received',
                Transaction::TYPE_MONEY_RECEIVED,
                $legs['currency'] === DualCurrency::USD,
                $data['received_on'] ?? now()->toDateString(),
                $data['reference'] ?? null,
                $legs['currency'],
                DualCurrency::primaryAmount($legs),
            );

            $depositTxn = $deposit['deposit_transaction'];

            $receipt = ProjectReceipt::query()->create([
                'project_id' => $project->id,
                'transaction_id' => $depositTxn->id,
                'amount_iqd' => $legs['amount_iqd'],
                'amount_usd' => $legs['amount_usd'],
                'exchange_rate' => 0,
                'received_on' => $data['received_on'],
                'source' => $data['source'] ?? null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'entered_by' => $enteredBy,
            ]);

            $causer = $enteredBy ? \App\Models\User::query()->find($enteredBy) : null;
            $this->audit->log(
                AuditActions::PROJECT_RECEIPT,
                sprintf(
                    'Project receipt %.2f %s → project #%d',
                    DualCurrency::primaryAmount($legs),
                    $legs['currency'],
                    $project->id,
                ),
                $receipt,
                [
                    'project_id' => $project->id,
                    'receipt_id' => $receipt->id,
                    'transaction_id' => $depositTxn->id,
                    'currency' => $legs['currency'],
                    'amount_iqd' => $legs['amount_iqd'],
                    'amount_usd' => $legs['amount_usd'],
                    'source' => $receipt->source,
                    'reference' => $receipt->reference,
                ],
                $causer,
            );

            return [
                'receipt' => $receipt,
                'deposit' => $deposit,
                'summary' => $this->summary($project->fresh()),
            ];
        });
    }
}
