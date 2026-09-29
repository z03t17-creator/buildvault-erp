<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Payout extends Model
{
    use SoftDeletes;

    public const CATEGORY_EXPENSES = 'expenses';

    public const CATEGORY_PAYROLL = 'payroll';

    public const CATEGORY_RETENTION = 'retention';

    public const CATEGORY_PENALTY = 'penalty';

    public const CATEGORY_PROFIT = 'profit';

    /** @var list<string> */
    public const CATEGORIES = [
        self::CATEGORY_EXPENSES,
        self::CATEGORY_PAYROLL,
        self::CATEGORY_RETENTION,
        self::CATEGORY_PENALTY,
        self::CATEGORY_PROFIT,
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_RECONCILED = 'reconciled';

    public const STATUS_REJECTED = 'rejected';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_RECONCILED,
        self::STATUS_REJECTED,
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'amount_iqd' => 0,
        'exchange_rate' => 0,
        'retention_holdback' => 0,
        'status' => self::STATUS_PENDING,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'vault_id',
        'project_id',
        'worker_id',
        'floor_id',
        'category',
        'amount_usd',
        'amount_iqd',
        'exchange_rate',
        'retention_holdback',
        'status',
        'notes',
        'approved_at',
        'reconciled_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_usd' => 'decimal:2',
            'amount_iqd' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'retention_holdback' => 'decimal:2',
            'approved_at' => 'datetime',
            'reconciled_at' => 'datetime',
        ];
    }

    public function vault(): BelongsTo
    {
        return $this->belongsTo(Vault::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function retentionHolds(): HasMany
    {
        return $this->hasMany(RetentionHold::class);
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(Penalty::class);
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'reference');
    }
}
