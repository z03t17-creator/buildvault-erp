<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Expense extends Model
{
    use SoftDeletes;

    public const CATEGORY_MATERIALS = 'materials';

    public const CATEGORY_TRANSPORTATION = 'transportation';

    public const CATEGORY_EQUIPMENT = 'equipment';

    public const CATEGORY_LABOR = 'labor';

    public const CATEGORY_FOOD = 'food';

    public const CATEGORY_FUEL = 'fuel';

    public const CATEGORY_MAINTENANCE = 'maintenance';

    public const CATEGORY_OFFICE = 'office';

    public const CATEGORY_OTHER = 'other';

    /** @var list<string> */
    public const CATEGORIES = [
        self::CATEGORY_MATERIALS,
        self::CATEGORY_TRANSPORTATION,
        self::CATEGORY_EQUIPMENT,
        self::CATEGORY_LABOR,
        self::CATEGORY_FOOD,
        self::CATEGORY_FUEL,
        self::CATEGORY_MAINTENANCE,
        self::CATEGORY_OFFICE,
        self::CATEGORY_OTHER,
    ];

    public const PAYMENT_CASH = 'cash';

    public const PAYMENT_BANK_TRANSFER = 'bank_transfer';

    public const PAYMENT_CHEQUE = 'cheque';

    public const PAYMENT_CARD = 'card';

    public const PAYMENT_OTHER = 'other';

    /** @var list<string> */
    public const PAYMENT_METHODS = [
        self::PAYMENT_CASH,
        self::PAYMENT_BANK_TRANSFER,
        self::PAYMENT_CHEQUE,
        self::PAYMENT_CARD,
        self::PAYMENT_OTHER,
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_HELD = 'held';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_HELD,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'amount_usd' => 0,
        'amount_iqd' => 0,
        'exchange_rate' => 0,
        'currency' => 'IQD',
        'approval_status' => self::STATUS_PENDING,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'vault_id',
        'category',
        'amount_iqd',
        'amount_usd',
        'exchange_rate',
        'currency',
        'expense_date',
        'supplier',
        'payment_method',
        'document_id',
        'description',
        'approval_status',
        'pay_ability_notes',
        'approved_at',
        'approved_by',
        'held_at',
        'held_by',
        'created_by',
        'transaction_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_iqd' => 'decimal:2',
            'amount_usd' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'expense_date' => 'date',
            'approved_at' => 'datetime',
            'held_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function vault(): BelongsTo
    {
        return $this->belongsTo(Vault::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'reference');
    }

    public function isPending(): bool
    {
        return $this->approval_status === self::STATUS_PENDING;
    }

    public function isHeld(): bool
    {
        return $this->approval_status === self::STATUS_HELD;
    }

    public function isAwaitingPayAbility(): bool
    {
        return in_array($this->approval_status, [self::STATUS_PENDING, self::STATUS_HELD], true);
    }

    public function holder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'held_by');
    }
}
