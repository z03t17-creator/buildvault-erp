<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes;

    /** Legacy / generic vault credit (also used for non-receipt deposits). */
    public const TYPE_DEPOSIT = 'deposit';

    /** Client money received into the vault (project receipt). */
    public const TYPE_MONEY_RECEIVED = 'money_received';

    /** Optional pool-split helper entry (non-cash). */
    public const TYPE_ALLOCATION = 'allocation';

    /** Legacy / generic vault debit. */
    public const TYPE_WITHDRAWAL = 'withdrawal';

    /** Approved project expense (cash out). */
    public const TYPE_EXPENSE = 'expense';

    /** Payroll payout (cash out). */
    public const TYPE_PAYROLL = 'payroll';

    /** Employee advance / سلفە (cash out). */
    public const TYPE_ADVANCE = 'advance';

    /** Insurance hold release / retention accounting. */
    public const TYPE_INSURANCE = 'insurance';

    /** Penalty pool / deduction accounting. */
    public const TYPE_PENALTY = 'penalty';

    /** Optional stock purchase paid from vault (cash out). */
    public const TYPE_STOCK_PURCHASE = 'stock_purchase';

    /** Vault ↔ vault or internal transfer. */
    public const TYPE_TRANSFER = 'transfer';

    /** Legacy non-cash / misc adjustment. */
    public const TYPE_ADJUSTMENT = 'adjustment';

    /** Explicit audited FX conversion leg (Phase 1+). */
    public const TYPE_FX_CONVERSION = 'fx_conversion';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_DEPOSIT,
        self::TYPE_MONEY_RECEIVED,
        self::TYPE_ALLOCATION,
        self::TYPE_WITHDRAWAL,
        self::TYPE_EXPENSE,
        self::TYPE_PAYROLL,
        self::TYPE_ADVANCE,
        self::TYPE_INSURANCE,
        self::TYPE_PENALTY,
        self::TYPE_STOCK_PURCHASE,
        self::TYPE_TRANSFER,
        self::TYPE_ADJUSTMENT,
        self::TYPE_FX_CONVERSION,
    ];

    /**
     * Types that increase vault cash balance.
     *
     * @var list<string>
     */
    public const CASH_INFLOW_TYPES = [
        self::TYPE_DEPOSIT,
        self::TYPE_MONEY_RECEIVED,
    ];

    /**
     * Types that decrease vault cash balance.
     *
     * @var list<string>
     */
    public const CASH_OUTFLOW_TYPES = [
        self::TYPE_WITHDRAWAL,
        self::TYPE_EXPENSE,
        self::TYPE_PAYROLL,
        self::TYPE_ADVANCE,
        self::TYPE_STOCK_PURCHASE,
        self::TYPE_TRANSFER,
    ];

    /**
     * Non-cash ledger rows (pool helpers / accounting) — do not move vault cash.
     *
     * @var list<string>
     */
    public const NON_CASH_TYPES = [
        self::TYPE_ALLOCATION,
        self::TYPE_ADJUSTMENT,
        self::TYPE_INSURANCE,
        self::TYPE_PENALTY,
        self::TYPE_FX_CONVERSION,
    ];

    /**
     * Types counted as money received for project financials.
     *
     * @var list<string>
     */
    public const MONEY_RECEIVED_TYPES = [
        self::TYPE_DEPOSIT,
        self::TYPE_MONEY_RECEIVED,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'vault_id',
        'project_id',
        'type',
        'direction',
        'occurred_on',
        'amount_usd',
        'amount_iqd',
        'exchange_rate',
        'balance_after_usd',
        'balance_after_iqd',
        'description',
        'reference_code',
        'reference_type',
        'reference_id',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'amount_usd' => 'decimal:2',
            'amount_iqd' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'balance_after_usd' => 'decimal:2',
            'balance_after_iqd' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Transaction $txn): void {
            if ($txn->occurred_on === null) {
                $txn->occurred_on = now()->toDateString();
            }
        });
    }

    public function vault(): BelongsTo
    {
        return $this->belongsTo(Vault::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function isCashInflow(): bool
    {
        return in_array($this->type, self::CASH_INFLOW_TYPES, true);
    }

    public function isCashOutflow(): bool
    {
        return in_array($this->type, self::CASH_OUTFLOW_TYPES, true);
    }

    public function isNonCash(): bool
    {
        return in_array($this->type, self::NON_CASH_TYPES, true);
    }

    /**
     * Signed IQD effect on vault cash (+ in, − out, 0 non-cash).
     */
    public function signedAmountIqd(): float
    {
        $amount = round((float) $this->amount_iqd, 2);

        if ($this->isCashInflow()) {
            return $amount;
        }

        if ($this->isCashOutflow()) {
            return -1 * $amount;
        }

        return 0.0;
    }

    /**
     * Signed USD effect on vault cash (+ in, − out, 0 non-cash).
     */
    public function signedAmountUsd(): float
    {
        $amount = round((float) $this->amount_usd, 2);

        if ($this->isCashInflow()) {
            return $amount;
        }

        if ($this->isCashOutflow()) {
            return -1 * $amount;
        }

        return 0.0;
    }

    /**
     * Map a payout category to a Phase 12 ledger type.
     *
     * Payroll / expenses get specific cash types. Penalty & retention
     * category payouts remain generic withdrawals (cash out); TYPE_PENALTY
     * / TYPE_INSURANCE are reserved for non-cash pool accounting rows.
     */
    public static function typeForPayoutCategory(string $category): string
    {
        return match ($category) {
            Payout::CATEGORY_PAYROLL => self::TYPE_PAYROLL,
            Payout::CATEGORY_EXPENSES => self::TYPE_EXPENSE,
            default => self::TYPE_WITHDRAWAL,
        };
    }

    /**
     * @param  Builder<Transaction>  $query
     * @return Builder<Transaction>
     */
    public function scopeForVault(Builder $query, int $vaultId): Builder
    {
        return $query->where('vault_id', $vaultId);
    }

    /**
     * @param  Builder<Transaction>  $query
     * @return Builder<Transaction>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * @param  Builder<Transaction>  $query
     * @return Builder<Transaction>
     */
    public function scopeCashAffecting(Builder $query): Builder
    {
        return $query->whereIn('type', array_merge(self::CASH_INFLOW_TYPES, self::CASH_OUTFLOW_TYPES));
    }
}
