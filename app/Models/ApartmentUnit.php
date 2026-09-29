<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApartmentUnit extends Model
{
    use SoftDeletes;

    public const CATEGORY_MDF = 'mdf';

    public const CATEGORY_LAMINATE = 'laminate';

    public const CATEGORY_METXAL = 'metxal';

    public const CATEGORY_PACKET = 'packet';

    public const CATEGORY_ENTRANCE = 'entrance';

    /** @var list<string> */
    public const CATEGORIES = [
        self::CATEGORY_MDF,
        self::CATEGORY_LAMINATE,
        self::CATEGORY_METXAL,
        self::CATEGORY_PACKET,
        self::CATEGORY_ENTRANCE,
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_INSPECTED = 'inspected';

    /** @deprecated Use STATUS_COMPLETED */
    public const STATUS_DONE = self::STATUS_COMPLETED;

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_INSPECTED,
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'is_company_crew' => false,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'building_block_id',
        'tower_id',
        'floor_id',
        'unit_label',
        'floor_number',
        'category',
        'status',
        'assigned_worker_id',
        'is_company_crew',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'floor_number' => 'integer',
            'is_company_crew' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function buildingBlock(): BelongsTo
    {
        return $this->belongsTo(BuildingBlock::class);
    }

    public function tower(): BelongsTo
    {
        return $this->belongsTo(Tower::class);
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    public function assignedWorker(): BelongsTo
    {
        return $this->belongsTo(Worker::class, 'assigned_worker_id');
    }
}
