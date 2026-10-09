<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffStatement extends Model
{
    use SoftDeletes;

    public const STATUS_OPEN = 'open';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_VOID = 'void';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_SETTLED,
        self::STATUS_VOID,
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_OPEN,
        'earned_usd' => 0,
        'earned_iqd' => 0,
        'paid_usd' => 0,
        'paid_iqd' => 0,
        'remaining_usd' => 0,
        'remaining_iqd' => 0,
        'retention_held_usd' => 0,
        'retention_held_iqd' => 0,
        'advances_usd' => 0,
        'advances_iqd' => 0,
        'penalties_usd' => 0,
        'penalties_iqd' => 0,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'worker_id',
        'project_id',
        'period',
        'label',
        'earned_usd',
        'earned_iqd',
        'paid_usd',
        'paid_iqd',
        'remaining_usd',
        'remaining_iqd',
        'retention_held_usd',
        'retention_held_iqd',
        'advances_usd',
        'advances_iqd',
        'penalties_usd',
        'penalties_iqd',
        'status',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'earned_usd' => 'decimal:2',
            'earned_iqd' => 'decimal:2',
            'paid_usd' => 'decimal:2',
            'paid_iqd' => 'decimal:2',
            'remaining_usd' => 'decimal:2',
            'remaining_iqd' => 'decimal:2',
            'retention_held_usd' => 'decimal:2',
            'retention_held_iqd' => 'decimal:2',
            'advances_usd' => 'decimal:2',
            'advances_iqd' => 'decimal:2',
            'penalties_usd' => 'decimal:2',
            'penalties_iqd' => 'decimal:2',
        ];
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Recalc remaining from earned − paid (per currency; never blended).
     */
    public function recalculateRemaining(): void
    {
        $this->remaining_usd = round(
            (float) $this->earned_usd - (float) $this->paid_usd - (float) $this->retention_held_usd - (float) $this->advances_usd - (float) $this->penalties_usd,
            2,
        );
        $this->remaining_iqd = round(
            (float) $this->earned_iqd - (float) $this->paid_iqd - (float) $this->retention_held_iqd - (float) $this->advances_iqd - (float) $this->penalties_iqd,
            2,
        );
    }
}
