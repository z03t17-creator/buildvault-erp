<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Penalty extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_WAIVED = 'waived';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPLIED,
        self::STATUS_WAIVED,
    ];

    public const TYPE_LATE = 'late';

    public const TYPE_ABSENCE = 'absence';

    public const TYPE_DAMAGE = 'damage';

    public const TYPE_SAFETY = 'safety';

    public const TYPE_CONDUCT = 'conduct';

    public const TYPE_OTHER = 'other';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_LATE,
        self::TYPE_ABSENCE,
        self::TYPE_DAMAGE,
        self::TYPE_SAFETY,
        self::TYPE_CONDUCT,
        self::TYPE_OTHER,
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'deducted_from_payout' => false,
        'status' => self::STATUS_PENDING,
        'type' => self::TYPE_OTHER,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'worker_id',
        'project_id',
        'floor_id',
        'type',
        'reason',
        'notes',
        'amount_usd',
        'amount_iqd',
        'currency',
        'occurred_on',
        'deducted_from_payout',
        'payout_id',
        'status',
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
            'occurred_on' => 'date',
            'deducted_from_payout' => 'boolean',
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

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
