<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Simple vault ledger line — one currency per row, never blended.
 */
class VaultLine extends Model
{
    use SoftDeletes;

    public const KIND_ADVANCE = 'advance';

    public const KIND_SALARY = 'salary';

    public const KIND_EXPENSE = 'expense';

    public const KIND_JOB_PAY = 'job_pay';

    public const KIND_DAILY_PAY = 'daily_pay';

    public const KIND_UNIT_PAY = 'unit_pay';

    /** @var list<string> */
    public const KINDS = [
        self::KIND_ADVANCE,
        self::KIND_SALARY,
        self::KIND_EXPENSE,
        self::KIND_JOB_PAY,
        self::KIND_DAILY_PAY,
        self::KIND_UNIT_PAY,
    ];

    /** Staff payout kinds that can take a 10% staff_owed hold. */
    public const STAFF_HOLD_KINDS = [
        self::KIND_JOB_PAY,
        self::KIND_DAILY_PAY,
        self::KIND_UNIT_PAY,
        self::KIND_SALARY,
    ];

    public const SITE_VILLA = 'villa';

    public const SITE_BUILDING = 'building';

    /** @var list<string> */
    public const SITE_KINDS = [
        self::SITE_VILLA,
        self::SITE_BUILDING,
    ];

    public const HOLD_POOL_COMPANY_INSURANCE = 'company_insurance';

    public const HOLD_POOL_STAFF_OWED = 'staff_owed';

    public const HOLD_DAYS = 180;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'hold_amount' => 0,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'vault_id',
        'kind',
        'occurred_on',
        'amount',
        'currency',
        'project_id',
        'staff_id',
        'note',
        'expense_type',
        'purpose',
        'site_kind',
        'block',
        'zone',
        'floor',
        'apartment_number',
        'apartment_model',
        'villa_number',
        'area',
        'days_count',
        'day_rate',
        'hold_amount',
        'hold_pool',
        'unlock_date',
        'hold_released_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'amount' => 'decimal:2',
            'hold_amount' => 'decimal:2',
            'days_count' => 'decimal:2',
            'day_rate' => 'decimal:2',
            'unlock_date' => 'date',
            'hold_released_at' => 'datetime',
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

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(VaultLineItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function isMoneyIn(): bool
    {
        return $this->kind === self::KIND_ADVANCE;
    }

    public function usesStaffHoldPool(): bool
    {
        return in_array($this->kind, [
            self::KIND_JOB_PAY,
            self::KIND_DAILY_PAY,
            self::KIND_UNIT_PAY,
        ], true)
            || ($this->kind === self::KIND_SALARY && $this->hold_pool === self::HOLD_POOL_STAFF_OWED);
    }

    public function availablePortion(): float
    {
        return round((float) $this->amount - (float) $this->hold_amount, 2);
    }

    public function holdIsOpen(): bool
    {
        return (float) $this->hold_amount > 0
            && $this->hold_released_at === null
            && $this->hold_pool !== null;
    }

    public function companyHoldUnlocked(\DateTimeInterface|string|null $asOf = null): bool
    {
        if ($this->hold_pool !== self::HOLD_POOL_COMPANY_INSURANCE || (float) $this->hold_amount <= 0) {
            return false;
        }

        if ($this->unlock_date === null) {
            return false;
        }

        $asOf = $asOf ? \Carbon\Carbon::parse($asOf)->startOfDay() : now()->startOfDay();

        return $this->unlock_date->copy()->startOfDay()->lessThanOrEqualTo($asOf);
    }

    /**
     * @param  Builder<VaultLine>  $query
     * @return Builder<VaultLine>
     */
    public function scopeOfCurrency(Builder $query, string $currency): Builder
    {
        return $query->where('currency', strtoupper($currency));
    }

    /**
     * @param  Builder<VaultLine>  $query
     * @return Builder<VaultLine>
     */
    public function scopeOfKind(Builder $query, string $kind): Builder
    {
        return $query->where('kind', $kind);
    }

    /**
     * @param  Builder<VaultLine>  $query
     * @return Builder<VaultLine>
     */
    public function scopeStaffPays(Builder $query): Builder
    {
        return $query->whereIn('kind', [
            self::KIND_JOB_PAY,
            self::KIND_DAILY_PAY,
            self::KIND_UNIT_PAY,
        ]);
    }
}
