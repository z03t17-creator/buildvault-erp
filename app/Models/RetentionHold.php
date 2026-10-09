<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetentionHold extends Model
{
    use SoftDeletes;

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

    /** Default day-based maturity aligned with owner 10%/180-day rule. */
    public const MATURITY_DAYS = 180;

    public const LAYER_STAFF = 'staff';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_HOLDING,
        'layer' => self::LAYER_STAFF,
        'amount_iqd' => 0,
        'maturity_days' => self::MATURITY_DAYS,
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
        'amount_iqd',
        'layer',
        'maturity_days',
        'hold_start',
        'maturity_date',
        'status',
        'released_at',
        'released_amount_usd',
        'released_amount_iqd',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_usd' => 'decimal:2',
            'amount_iqd' => 'decimal:2',
            'hold_pct' => 'decimal:2',
            'maturity_days' => 'integer',
            'released_amount_usd' => 'decimal:2',
            'released_amount_iqd' => 'decimal:2',
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
