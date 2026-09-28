<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Legacy attendance rows. Table retained for existing DBs; unused by payroll/UI.
 */
class Attendance extends Model
{
    public const STATUS_PRESENT = 'present';

    public const STATUS_LATE = 'late';

    public const STATUS_ABSENT_UNEXCUSED = 'absent_unexcused';

    public const STATUS_LEAVE_PAID = 'leave_paid';

    public const STATUS_LEAVE_SICK = 'leave_sick';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PRESENT,
        self::STATUS_LATE,
        self::STATUS_ABSENT_UNEXCUSED,
        self::STATUS_LEAVE_PAID,
        self::STATUS_LEAVE_SICK,
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'late_minutes' => 0,
        'overtime_hours' => 0,
        'status' => self::STATUS_PRESENT,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'worker_id',
        'floor_id',
        'date',
        'check_in',
        'check_out',
        'late_minutes',
        'overtime_hours',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'late_minutes' => 'integer',
            'overtime_hours' => 'decimal:2',
        ];
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    public function isLeave(): bool
    {
        return in_array($this->status, [
            self::STATUS_LEAVE_PAID,
            self::STATUS_LEAVE_SICK,
        ], true);
    }
}
