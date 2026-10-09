<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    public const TYPE_WORKERS = 'workers';

    public const TYPE_PROJECTS = 'projects';

    public const TYPE_PAYOUTS = 'payouts';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_WORKERS,
        self::TYPE_PROJECTS,
        self::TYPE_PAYOUTS,
    ];

    public const MODE_PARTIAL = 'partial';

    public const MODE_ATOMIC = 'atomic';

    /** @var list<string> */
    public const MODES = [
        self::MODE_PARTIAL,
        self::MODE_ATOMIC,
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ROLLED_BACK = 'rolled_back';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        self::STATUS_ROLLED_BACK,
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'mode' => self::MODE_PARTIAL,
        'status' => self::STATUS_PENDING,
        'total_rows' => 0,
        'success_rows' => 0,
        'failed_rows' => 0,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'mode',
        'status',
        'original_filename',
        'stored_path',
        'total_rows',
        'success_rows',
        'failed_rows',
        'created_by',
        'started_at',
        'finished_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function details(): HasMany
    {
        return $this->hasMany(ImportDetail::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
