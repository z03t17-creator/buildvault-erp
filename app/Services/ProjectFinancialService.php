<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Payout;
use App\Models\Project;
use App\Models\ProjectReceipt;
use App\Models\Transaction;
use App\Models\Vault;
use App\Support\AuditActions;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Project-level IQD financial summary and money-received recording.
 *
 * material_cost_iqd = sum of stock-out line values (qty × unit purchase price) for the project.
 * Full Phase 11 project cost linking can deepen this later.
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
     * Light Phase 10 hook — stock-out purchase value attributed to the project.
     */
    public function materialCostIqd(Project $project): float
    {
        return $this->stock->materialCostForProject((int) $project->id);
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
            ->where('type', Transaction::TYPE_DEPOSIT)
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
     * Record client money received (IQD): vault deposit + project_receipt row.
     *
     * @param  array{
     *     amount_iqd: float|int|string,
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
        $amountIqd = round((float) $data['amount_iqd'], 2);
        if ($amountIqd <= 0) {
            throw new InvalidArgumentException('Receipt amount must be greater than zero.');
        }

        $rate = $this->exchangeRates->getUsdToIqd();
        if ($rate <= 0) {
            throw new InvalidArgumentException('Exchange rate must be greater than zero.');
        }

        $amountUsd = round($amountIqd / $rate, 2);
        if ($amountUsd <= 0) {
            throw new InvalidArgumentException('Converted USD amount must be greater than zero.');
        }

        $enteredBy = $data['entered_by'] ?? null;
        $description = trim(sprintf(
            'Project receipt%s%s',
            ! empty($data['source']) ? ' · '.$data['source'] : '',
            ! empty($data['reference']) ? ' · ref '.$data['reference'] : '',
        ));

        return DB::transaction(function () use (
            $project,
            $data,
            $amountIqd,
            $amountUsd,
            $rate,
            $enteredBy,
            $description,
        ) {
            $deposit = $this->vault->deposit(
                $project,
                $amountUsd,
                $data['vault'] ?? null,
                $enteredBy,
                $description !== 'Project receipt' ? $description : 'Project money received',
            );

            // Prefer the submitted IQD amount on the deposit ledger row (FX rounding).
            $depositTxn = $deposit['deposit_transaction'];
            $depositTxn->amount_iqd = $amountIqd;
            $depositTxn->exchange_rate = $rate;
            $depositTxn->save();

            $receipt = ProjectReceipt::query()->create([
                'project_id' => $project->id,
                'transaction_id' => $depositTxn->id,
                'amount_iqd' => $amountIqd,
                'amount_usd' => $amountUsd,
                'exchange_rate' => $rate,
                'received_on' => $data['received_on'],
                'source' => $data['source'] ?? null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'entered_by' => $enteredBy,
            ]);

            $causer = $enteredBy ? \App\Models\User::query()->find($enteredBy) : null;
            $this->audit->log(
                AuditActions::PROJECT_RECEIPT,
                sprintf('Project receipt %.2f IQD → project #%d', $amountIqd, $project->id),
                $receipt,
                [
                    'project_id' => $project->id,
                    'receipt_id' => $receipt->id,
                    'transaction_id' => $depositTxn->id,
                    'amount_iqd' => $amountIqd,
                    'amount_usd' => $amountUsd,
                    'exchange_rate' => $rate,
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
