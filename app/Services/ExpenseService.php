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
use App\Support\DualCurrency;
use Database\Seeders\VaultSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Project expenses: create pending → Accountant ability-to-pay approve/hold/reject.
 * Qasa single-leg amounts (unused currency = 0).
 */
class ExpenseService
{
    public function __construct(
        private readonly LiquidityService $liquidity,
        private readonly VaultBalanceService $balances,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{
     *     project_id: int,
     *     category: string,
     *     amount?: float|int|string,
     *     amount_iqd?: float|int|string,
     *     amount_usd?: float|int|string,
     *     currency?: string,
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

        $currency = strtoupper((string) ($data['currency'] ?? DualCurrency::IQD));
        $amount = $data['amount']
            ?? ($currency === DualCurrency::USD ? ($data['amount_usd'] ?? null) : ($data['amount_iqd'] ?? null));
        $legs = DualCurrency::legs($currency, $amount);

        $this->liquidity->assertCanPayCurrency(
            $project,
            Payout::CATEGORY_EXPENSES,
            $legs['currency'],
            DualCurrency::primaryAmount($legs),
            $vault,
        );

        return DB::transaction(function () use ($data, $project, $vault, $legs) {
            $documentId = null;
            if (! empty($data['receipt']) && $data['receipt'] instanceof UploadedFile) {
                $documentId = $this->storeReceipt($data['receipt'], $project, $data['created_by'] ?? null)->id;
            }

            return Expense::query()->create([
                'project_id' => $project->id,
                'vault_id' => $vault->id,
                'category' => (string) $data['category'],
                'amount_iqd' => $legs['amount_iqd'],
                'amount_usd' => $legs['amount_usd'],
                'exchange_rate' => $legs['exchange_rate'],
                'currency' => $legs['currency'],
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
     * @param  array<string, mixed>  $data
     */
    public function update(Expense $expense, array $data): Expense
    {
        if (! $expense->isAwaitingPayAbility()) {
            throw new InvalidArgumentException('Only pending or held expenses can be edited.');
        }

        $project = isset($data['project_id'])
            ? Project::query()->findOrFail($data['project_id'])
            : Project::query()->findOrFail($expense->project_id);

        $vault = isset($data['vault_id'])
            ? Vault::query()->findOrFail($data['vault_id'])
            : ($expense->vault ?? $this->zhakoVault());

        $currency = strtoupper((string) ($data['currency'] ?? $expense->currency ?? DualCurrency::IQD));
        $amount = $data['amount']
            ?? ($currency === DualCurrency::USD
                ? ($data['amount_usd'] ?? $expense->amount_usd)
                : ($data['amount_iqd'] ?? $expense->amount_iqd));
        $legs = DualCurrency::legs($currency, $amount);

        $this->liquidity->assertCanPayCurrency(
            $project,
            Payout::CATEGORY_EXPENSES,
            $legs['currency'],
            DualCurrency::primaryAmount($legs),
            $vault,
            excludeExpenseId: $expense->id,
        );

        return DB::transaction(function () use ($expense, $data, $project, $vault, $legs) {
            if (! empty($data['receipt']) && $data['receipt'] instanceof UploadedFile) {
                $doc = $this->storeReceipt($data['receipt'], $project, $expense->created_by);
                $expense->document_id = $doc->id;
            }

            $expense->fill([
                'project_id' => $project->id,
                'vault_id' => $vault->id,
                'category' => $data['category'] ?? $expense->category,
                'amount_iqd' => $legs['amount_iqd'],
                'amount_usd' => $legs['amount_usd'],
                'exchange_rate' => $legs['exchange_rate'],
                'currency' => $legs['currency'],
                'expense_date' => $data['expense_date'] ?? $expense->expense_date,
                'supplier' => array_key_exists('supplier', $data) ? $data['supplier'] : $expense->supplier,
                'payment_method' => array_key_exists('payment_method', $data) ? $data['payment_method'] : $expense->payment_method,
                'description' => array_key_exists('description', $data) ? $data['description'] : $expense->description,
                'approval_status' => Expense::STATUS_PENDING,
            ]);
            $expense->save();

            return $expense->fresh(['project', 'document', 'creator']);
        });
    }

    public function approve(Expense $expense, ?User $approver = null): Expense
    {
        if (! $expense->isAwaitingPayAbility()) {
            throw new InvalidArgumentException('Only pending or held expenses can be approved.');
        }

        return DB::transaction(function () use ($expense, $approver) {
            $expense = Expense::query()->lockForUpdate()->findOrFail($expense->id);
            $vault = Vault::query()->lockForUpdate()->findOrFail($expense->vault_id);
            $project = Project::query()->findOrFail($expense->project_id);

            $currency = strtoupper((string) ($expense->currency ?: (
                (float) $expense->amount_usd > 0 ? DualCurrency::USD : DualCurrency::IQD
            )));
            $amount = $currency === DualCurrency::USD
                ? (float) $expense->amount_usd
                : (float) $expense->amount_iqd;

            $ability = $this->liquidity->assertCanPayCurrency(
                $project,
                Payout::CATEGORY_EXPENSES,
                $currency,
                $amount,
                $vault,
                excludeExpenseId: $expense->id,
            );

            $amountUsd = $ability['amount_usd'];
            $amountIqd = $ability['amount_iqd'];

            if ($currency === DualCurrency::USD) {
                $column = LiquidityService::CATEGORY_POOL_COLUMNS[Payout::CATEGORY_EXPENSES];
                $allocation = ProjectAllocation::query()
                    ->where('project_id', $project->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $poolBefore = (float) $allocation->{$column};
                $allocation->{$column} = round($poolBefore - $amountUsd, 2);
                $allocation->save();

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
            }

            $txn = Transaction::query()->create([
                'vault_id' => $vault->id,
                'project_id' => $project->id,
                'type' => Transaction::TYPE_EXPENSE,
                'direction' => 'out',
                'occurred_on' => $expense->expense_date?->toDateString() ?? now()->toDateString(),
                'amount_usd' => $amountUsd,
                'amount_iqd' => $amountIqd,
                'exchange_rate' => 0,
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
            $this->balances->apply($txn, $vault);

            $expense->approval_status = Expense::STATUS_APPROVED;
            $expense->approved_at = now();
            $expense->approved_by = $approver?->id;
            $expense->transaction_id = $txn->id;
            $expense->save();

            $this->audit->log(
                AuditActions::EXPENSE_APPROVED,
                sprintf(
                    'Expense #%d approved (%.2f %s, %s)',
                    $expense->id,
                    DualCurrency::primaryAmount([
                        'currency' => $currency,
                        'amount_usd' => $amountUsd,
                        'amount_iqd' => $amountIqd,
                    ]),
                    $currency,
                    $expense->category,
                ),
                $expense,
                [
                    'expense_id' => $expense->id,
                    'project_id' => $project->id,
                    'category' => $expense->category,
                    'currency' => $currency,
                    'amount_iqd' => $amountIqd,
                    'amount_usd' => $amountUsd,
                    'available_usd' => $ability['available_usd'],
                    'available_iqd' => $ability['available_iqd'],
                    'transaction_id' => $txn->id,
                ],
                $approver,
            );

            return $expense->fresh(['project', 'document', 'creator', 'approver', 'transaction']);
        });
    }

    public function hold(Expense $expense, ?string $notes = null, ?User $actor = null): Expense
    {
        if (! $expense->isAwaitingPayAbility()) {
            throw new InvalidArgumentException('Only pending or held expenses can be held.');
        }

        $expense->approval_status = Expense::STATUS_HELD;
        $expense->held_at = now();
        $expense->held_by = $actor?->id;
        $expense->pay_ability_notes = $notes;
        $expense->save();

        $this->audit->log(
            AuditActions::EXPENSE_HELD,
            sprintf('Expense #%d held (ability to pay)', $expense->id),
            $expense,
            [
                'expense_id' => $expense->id,
                'notes' => $notes,
            ],
            $actor,
        );

        return $expense->fresh();
    }

    public function reject(Expense $expense, ?string $notes = null, ?User $actor = null): Expense
    {
        if (! $expense->isAwaitingPayAbility()) {
            throw new InvalidArgumentException('Only pending or held expenses can be rejected.');
        }

        $expense->approval_status = Expense::STATUS_REJECTED;
        $expense->pay_ability_notes = $notes;
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
                'amount_usd' => (float) $expense->amount_usd,
                'notes' => $notes,
            ],
            $actor,
        );

        return $expense->fresh();
    }

    public function softDelete(Expense $expense, ?User $actor = null): void
    {
        if ($expense->approval_status === Expense::STATUS_APPROVED && $expense->transaction_id) {
            $txn = Transaction::query()->find($expense->transaction_id);
            if ($txn) {
                $this->balances->softDeleteAndRebuild($txn);
            }
        }

        $expense->delete();

        $this->audit->log(
            AuditActions::VAULT_SOFT_DELETE_REBUILD,
            sprintf('Expense #%d soft-deleted', $expense->id),
            $expense,
            ['expense_id' => $expense->id],
            $actor,
        );
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
