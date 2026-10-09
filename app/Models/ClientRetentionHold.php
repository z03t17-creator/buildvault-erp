<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientRetentionHold extends Model
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

    public const DEFAULT_HOLD_PCT = 10.0;

    public const DEFAULT_MATURITY_DAYS = 180;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_HOLDING,
        'hold_pct' => self::DEFAULT_HOLD_PCT,
        'maturity_days' => self::DEFAULT_MATURITY_DAYS,
        'amount_usd' => 0,
        'amount_iqd' => 0,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'client_advance_id',
        'project_id',
        'vault_id',
        'amount_usd',
        'amount_iqd',
        'hold_pct',
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
            'hold_start' => 'date',
            'maturity_date' => 'date',
            'released_at' => 'datetime',
            'released_amount_usd' => 'decimal:2',
            'released_amount_iqd' => 'decimal:2',
        ];
    }

    public static function maturityFrom(Carbon|string $holdStart, ?int $days = null): Carbon
    {
        $days ??= self::DEFAULT_MATURITY_DAYS;

        return Carbon::parse($holdStart)->startOfDay()->addDays(max(1, $days));
    }

    public function clientAdvance(): BelongsTo
    {
        return $this->belongsTo(ClientAdvance::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function vault(): BelongsTo
    {
        return $this->belongsTo(Vault::class);
    }
}
