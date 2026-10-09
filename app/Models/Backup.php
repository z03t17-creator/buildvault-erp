<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Backup extends Model
{
    public const TYPE_FULL = 'full';

    public const TYPE_DATABASE = 'database';

    public const TYPE_FILES = 'files';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_FULL,
        self::TYPE_DATABASE,
        self::TYPE_FILES,
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_RUNNING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
    ];

    public const DISK = 'backups';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => self::TYPE_FULL,
        'disk' => self::DISK,
        'status' => self::STATUS_PENDING,
        'size_bytes' => 0,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'filename',
        'disk',
        'location',
        'size_bytes',
        'status',
        'message',
        'created_by',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fileExists(): bool
    {
        return $this->location
            && Storage::disk($this->disk ?: self::DISK)->exists($this->location);
    }

    public function isDownloadable(): bool
    {
        return $this->status === self::STATUS_COMPLETED && $this->fileExists();
    }
}
