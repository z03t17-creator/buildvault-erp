<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetentionHold extends Model
{
    public const STATUS_HOLDING = 'holding';

    public const STATUS_MATURED = 'matured';

    public const STATUS_RELEASED = 'released';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_HOLDING,
        self::STATUS_MATURED,
        self::STATUS_RELEASED,
    ];

    /**
     * Default months until insurance is returned to staff (overridable via settings).
     */
    public const MATURITY_MONTHS = 6;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_HOLDING,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'vault_id',
        'project_id',
        'worker_id',
        'payout_id',
        'pay_period',
        'hold_pct',
        'amount_usd',
        'hold_start',
        'maturity_date',
        'status',
        'released_at',
        'released_amount_usd',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_usd' => 'decimal:2',
            'hold_pct' => 'decimal:2',
            'released_amount_usd' => 'decimal:2',
            'hold_start' => 'date',
            'maturity_date' => 'date',
            'released_at' => 'datetime',
        ];
    }

    /**
     * Maturity = hold_start + configured months (default 6; admin-editable).
     */
    public static function maturityFrom(Carbon|string $holdStart, ?int $months = null): Carbon
    {
        $months ??= app(\App\Services\InsuranceSettings::class)->maturityMonths();

        return Carbon::parse($holdStart)->startOfDay()->addMonthsNoOverflow(max(1, $months));
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

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }
}
