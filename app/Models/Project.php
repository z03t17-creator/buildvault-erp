<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Project extends Model
{
    public const STATUS_PLANNING = 'planning';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ON_HOLD = 'on_hold';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_ARCHIVED = 'archived';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PLANNING,
        self::STATUS_ACTIVE,
        self::STATUS_ON_HOLD,
        self::STATUS_COMPLETED,
        self::STATUS_ARCHIVED,
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'total_budget_usd' => 0,
        'allocation_expenses_pct' => 45.00,
        'allocation_payroll_pct' => 30.00,
        'allocation_insurance_pct' => 10.00,
        'allocation_penalty_pct' => 5.00,
        'allocation_profit_pct' => 10.00,
        'status' => self::STATUS_PLANNING,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'logo_path',
        'location',
        'total_budget_usd',
        'allocation_expenses_pct',
        'allocation_payroll_pct',
        'allocation_insurance_pct',
        'allocation_penalty_pct',
        'allocation_profit_pct',
        'start_date',
        'end_date',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_budget_usd' => 'decimal:2',
            'allocation_expenses_pct' => 'decimal:2',
            'allocation_payroll_pct' => 'decimal:2',
            'allocation_insurance_pct' => 'decimal:2',
            'allocation_penalty_pct' => 'decimal:2',
            'allocation_profit_pct' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function towers(): HasMany
    {
        return $this->hasMany(Tower::class);
    }

    public function floors(): HasManyThrough
    {
        return $this->hasManyThrough(Floor::class, Tower::class);
    }

    public function workers(): HasMany
    {
        return $this->hasMany(Worker::class);
    }

    public function allocation(): HasOne
    {
        return $this->hasOne(ProjectAllocation::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function retentionHolds(): HasMany
    {
        return $this->hasMany(RetentionHold::class);
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(Penalty::class);
    }
}

