<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionRecord extends Model
{
    public const UNIT_APARTMENT = 'apartment';

    public const UNIT_VILLA = 'villa';

    public const UNIT_BUILDING = 'building';

    public const UNIT_FLOOR = 'floor';

    public const UNIT_ROOM = 'room';

    public const UNIT_OTHER = 'other';

    /** @var list<string> */
    public const UNIT_TYPES = [
        self::UNIT_APARTMENT,
        self::UNIT_VILLA,
        self::UNIT_BUILDING,
        self::UNIT_FLOOR,
        self::UNIT_ROOM,
        self::UNIT_OTHER,
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'unit_type' => self::UNIT_APARTMENT,
        'assigned' => 0,
        'completed' => 0,
        'received' => 0,
        'remaining' => 0,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'worker_id',
        'project_id',
        'unit_type',
        'unit_label',
        'assigned',
        'completed',
        'received',
        'remaining',
        'recorded_on',
        'notes',
        'entered_by',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'progress_pct',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned' => 'decimal:2',
            'completed' => 'decimal:2',
            'received' => 'decimal:2',
            'remaining' => 'decimal:2',
            'recorded_on' => 'date',
        ];
    }

    /**
     * Progress % = completed / assigned * 100 when assigned > 0; else 0.
     */
    protected function progressPct(): Attribute
    {
        return Attribute::get(function (): float {
            $assigned = (float) $this->assigned;
            if ($assigned <= 0) {
                return 0.0;
            }

            return round(((float) $this->completed / $assigned) * 100, 2);
        });
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}
