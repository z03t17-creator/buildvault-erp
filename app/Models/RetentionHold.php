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
     * Months until insurance is returned to staff (locked product rule).
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
        'amount_usd',
        'hold_start',
        'maturity_date',
        'status',
        'released_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_usd' => 'decimal:2',
            'hold_start' => 'date',
            'maturity_date' => 'date',
            'released_at' => 'datetime',
        ];
    }

    /**
     * Default maturity = hold_start + 6 months (shared insurance reserve).
     */
    public static function maturityFrom(Carbon|string $holdStart): Carbon
    {
        return Carbon::parse($holdStart)->startOfDay()->addMonthsNoOverflow(self::MATURITY_MONTHS);
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
