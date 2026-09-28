<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Expense;
use App\Models\Payout;
use App\Models\Project;
use App\Models\ProjectAllocation;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vault;
use App\Support\AuditActions;
use Database\Seeders\VaultSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Project expenses (IQD): create pending → approve posts vault outflow from the
 * expenses pool. Does not create Payout rows (avoids double-counting vs ledger).
 */
class ExpenseService
{
    public function __construct(
        private readonly LiquidityService $liquidity,
        private readonly ExchangeRateService $exchangeRates,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{
     *     project_id: int,
     *     category: string,
     *     amount_iqd: float|int|string,
     *     expense_date: string,
     *     supplier?: ?string,
     *     payment_method?: ?string,
     *     description?: ?string,
     *     vault_id?: ?int,
     *     created_by?: ?int,
     *     receipt?: ?UploadedFile,
     * }  $data
     */
    public function create(array $data): Expense
    {
        $project = Project::query()->findOrFail($data['project_id']);
        $vault = isset($data['vault_id'])
            ? Vault::query()->findOrFail($data['vault_id'])
            : $this->zhakoVault();

        $amountIqd = round((float) $data['amount_iqd'], 2);
        if ($amountIqd <= 0) {
            throw new InvalidArgumentException('Expense amount must be greater than zero.');
        }

        $rate = $this->exchangeRates->getUsdToIqd();
        if ($rate <= 0) {
            throw new InvalidArgumentException('Exchange rate must be greater than zero.');
        }

        $amountUsd = round($amountIqd / $rate, 2);
        if ($amountUsd <= 0) {
            throw new InvalidArgumentException('Converted USD amount must be greater than zero.');
        }

        $this->liquidity->assertCanPay($project, Payout::CATEGORY_EXPENSES, $amountUsd, $vault);

        return DB::transaction(function () use ($data, $project, $vault, $amountIqd, $amountUsd, $rate) {
            $documentId = null;
            if (! empty($data['receipt']) && $data['receipt'] instanceof UploadedFile) {
                $documentId = $this->storeReceipt($data['receipt'], $project, $data['created_by'] ?? null)->id;
            }

            return Expense::query()->create([
                'project_id' => $project->id,
                'vault_id' => $vault->id,
                'category' => (string) $data['category'],
                'amount_iqd' => $amountIqd,
                'amount_usd' => $amountUsd,
                'exchange_rate' => $rate,
                'expense_date' => $data['expense_date'],
                'supplier' => $data['supplier'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'document_id' => $documentId,
                'description' => $data['description'] ?? null,
                'approval_status' => Expense::STATUS_PENDING,
                'created_by' => $data['created_by'] ?? null,
            ]);
        });
    }

    /**
     * @param  array{
     *     project_id?: int,
     *     category?: string,
     *     amount_iqd?: float|int|string,
     *     expense_date?: string,
     *     supplier?: ?string,
     *     payment_method?: ?string,
     *     description?: ?string,
     *     vault_id?: ?int,
     *     receipt?: ?UploadedFile,
     * }  $data
     */
    public function update(Expense $expense, array $data): Expense
    {
        if ($expense->approval_status !== Expense::STATUS_PENDING) {
            throw new InvalidArgumentException('Only pending expenses can be edited.');
        }

        $project = isset($data['project_id'])
            ? Project::query()->findOrFail($data['project_id'])
            : Project::query()->findOrFail($expense->project_id);

        $vault = isset($data['vault_id'])
            ? Vault::query()->findOrFail($data['vault_id'])
            : ($expense->vault ?? $this->zhakoVault());

        $amountIqd = array_key_exists('amount_iqd', $data)
            ? round((float) $data['amount_iqd'], 2)
            : round((float) $expense->amount_iqd, 2);

        if ($amountIqd <= 0) {
            throw new InvalidArgumentException('Expense amount must be greater than zero.');
        }

        $rate = $this->exchangeRates->getUsdToIqd();
        $amountUsd = round($amountIqd / $rate, 2);

        // Exclude this expense from pending reservation while re-checking liquidity.
        $this->liquidity->assertCanPay(
            $project,
            Payout::CATEGORY_EXPENSES,
            $amountUsd,
            $vault,
            excludeExpenseId: $expense->id,
        );

        return DB::transaction(function () use ($expense, $data, $project, $vault, $amountIqd, $amountUsd, $rate) {
            if (! empty($data['receipt']) && $data['receipt'] instanceof UploadedFile) {
                $doc = $this->storeReceipt($data['receipt'], $project, $expense->created_by);
                $expense->document_id = $doc->id;
            }

            $expense->fill([
                'project_id' => $project->id,
                'vault_id' => $vault->id,
                'category' => $data['category'] ?? $expense->category,
                'amount_iqd' => $amountIqd,
                'amount_usd' => $amountUsd,
                'exchange_rate' => $rate,
                'expense_date' => $data['expense_date'] ?? $expense->expense_date,
                'supplier' => array_key_exists('supplier', $data) ? $data['supplier'] : $expense->supplier,
                'payment_method' => array_key_exists('payment_method', $data) ? $data['payment_method'] : $expense->payment_method,
                'description' => array_key_exists('description', $data) ? $data['description'] : $expense->description,
            ]);
            $expense->save();

            return $expense->fresh(['project', 'document', 'creator']);
        });
    }

    /**
     * Approve pending expense: deduct expenses pool + vault cash, write withdrawal.
     */
    public function approve(Expense $expense, ?User $approver = null): Expense
    {
        if ($expense->approval_status !== Expense::STATUS_PENDING) {
            throw new InvalidArgumentException('Only pending expenses can be approved.');
        }

        return DB::transaction(function () use ($expense, $approver) {
            $expense = Expense::query()->lockForUpdate()->findOrFail($expense->id);
            $vault = Vault::query()->lockForUpdate()->findOrFail($expense->vault_id);
            $project = Project::query()->findOrFail($expense->project_id);

            $amountUsd = round((float) $expense->amount_usd, 2);
            $amountIqd = round((float) $expense->amount_iqd, 2);

            $pool = $this->liquidity->poolAvailableUsd($project, Payout::CATEGORY_EXPENSES);
            if ($amountUsd > $pool) {
                throw new InvalidArgumentException(sprintf(
                    'Cannot approve: expenses pool has %.2f USD but expense needs %.2f USD.',
                    $pool,
                    $amountUsd,
                ));
            }

            if ($amountUsd > (float) $vault->balance_usd) {
                throw new InvalidArgumentException('Cannot approve: vault cash balance insufficient for expense.');
            }

            $column = LiquidityService::CATEGORY_POOL_COLUMNS[Payout::CATEGORY_EXPENSES];
            $allocation = ProjectAllocation::query()
                ->where('project_id', $project->id)
                ->lockForUpdate()
                ->firstOrFail();

            $poolBefore = (float) $allocation->{$column};
            $allocation->{$column} = round($poolBefore - $amountUsd, 2);
            $allocation->save();

            $rate = (float) $expense->exchange_rate ?: $this->exchangeRates->getUsdToIqd();

            $vault->balance_usd = round((float) $vault->balance_usd - $amountUsd, 2);
            $vault->balance_iqd = round((float) $vault->balance_iqd - $amountIqd, 2);
            $vault->save();

            $txn = Transaction::query()->create([
                'vault_id' => $vault->id,
                'project_id' => $project->id,
                'type' => Transaction::TYPE_EXPENSE,
                'occurred_on' => $expense->expense_date?->toDateString() ?? now()->toDateString(),
                'amount_usd' => $amountUsd,
                'amount_iqd' => $amountIqd,
                'exchange_rate' => $rate,
                'description' => sprintf(
                    'Expense #%d approved (%s)%s',
                    $expense->id,
                    $expense->category,
                    $expense->supplier ? ' · '.$expense->supplier : '',
                ),
                'reference_code' => 'EXP-'.$expense->id,
                'reference_type' => $expense->getMorphClass(),
                'reference_id' => $expense->id,
                'created_by' => $approver?->id ?? $expense->created_by,
            ]);

            $expense->approval_status = Expense::STATUS_APPROVED;
            $expense->approved_at = now();
            $expense->approved_by = $approver?->id;
            $expense->transaction_id = $txn->id;
            $expense->save();

            $this->audit->log(
                AuditActions::EXPENSE_APPROVED,
                sprintf('Expense #%d approved (%.2f IQD, %s)', $expense->id, $amountIqd, $expense->category),
                $expense,
                [
                    'expense_id' => $expense->id,
                    'project_id' => $project->id,
                    'category' => $expense->category,
                    'amount_iqd' => $amountIqd,
                    'amount_usd' => $amountUsd,
                    'transaction_id' => $txn->id,
                ],
                $approver,
            );

            $this->audit->log(
                AuditActions::ALLOCATION_CHANGED,
                sprintf('Allocation pool %s reduced by %.2f USD (expense #%d)', $column, $amountUsd, $expense->id),
                $allocation,
                [
                    'project_id' => $project->id,
                    'pool' => $column,
                    'before' => $poolBefore,
                    'after' => (float) $allocation->{$column},
                    'delta_usd' => -$amountUsd,
                    'reason' => 'expense_approve',
                    'expense_id' => $expense->id,
                ],
                $approver,
            );

            return $expense->fresh(['project', 'document', 'creator', 'approver', 'transaction']);
        });
    }

    public function reject(Expense $expense, ?string $notes = null, ?User $actor = null): Expense
    {
        if ($expense->approval_status !== Expense::STATUS_PENDING) {
            throw new InvalidArgumentException('Only pending expenses can be rejected.');
        }

        $expense->approval_status = Expense::STATUS_REJECTED;
        if ($notes !== null && $notes !== '') {
            $expense->description = trim(
                ($expense->description ? $expense->description."\n" : '').'Rejected: '.$notes,
            );
        }
        $expense->save();

        $this->audit->log(
            AuditActions::EXPENSE_REJECTED,
            sprintf('Expense #%d rejected', $expense->id),
            $expense,
            [
                'expense_id' => $expense->id,
                'project_id' => $expense->project_id,
                'amount_iqd' => (float) $expense->amount_iqd,
                'notes' => $notes,
            ],
            $actor,
        );

        return $expense->fresh();
    }

    protected function storeReceipt(UploadedFile $file, Project $project, ?int $uploadedBy): Document
    {
        $directory = sprintf('%d/%s', $project->id, Document::TYPE_RECEIPT);
        $filename = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($directory, $filename, Document::DISK);

        return Document::query()->create([
            'project_id' => $project->id,
            'worker_id' => null,
            'type' => Document::TYPE_RECEIPT,
            'title' => 'Expense receipt',
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
            'size_bytes' => $file->getSize() ?: 0,
            'uploaded_by' => $uploadedBy,
        ]);
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
