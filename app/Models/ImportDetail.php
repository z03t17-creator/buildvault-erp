<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ImportDetail extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_VALID = 'valid';

    public const STATUS_INVALID = 'invalid';

    public const STATUS_IMPORTED = 'imported';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_ROLLED_BACK = 'rolled_back';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_VALID,
        self::STATUS_INVALID,
        self::STATUS_IMPORTED,
        self::STATUS_SKIPPED,
        self::STATUS_ROLLED_BACK,
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'import_id',
        'row_number',
        'status',
        'payload',
        'errors',
        'record_type',
        'record_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'errors' => 'array',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function record(): MorphTo
    {
        return $this->morphTo();
    }
}
