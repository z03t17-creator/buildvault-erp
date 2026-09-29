<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Worker (employee) attendance — Stock Manager is the primary writer (Phase 4 UI).
 * Table + model ready in Phase 1; late > 30m → forfeit_day + penalty link.
 */
class Attendance extends Model
{
    use SoftDeletes;

    public const STATUS_PRESENT = 'present';

    public const STATUS_LATE = 'late';

    public const STATUS_ABSENT_UNEXCUSED = 'absent_unexcused';

    public const STATUS_LEAVE_PAID = 'leave_paid';

    public const STATUS_LEAVE_SICK = 'leave_sick';

    /** Late threshold (minutes) that triggers a full-day salary forfeit. */
    public const LATE_FORFEIT_MINUTES = 30;

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
        'forfeit_day' => false,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'worker_id',
        'project_id',
        'floor_id',
        'date',
        'check_in',
        'check_out',
        'shift_start',
        'late_minutes',
        'overtime_hours',
        'status',
        'forfeit_day',
        'penalty_id',
        'entered_by',
        'notes',
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
            'forfeit_day' => 'boolean',
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

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function penalty(): BelongsTo
    {
        return $this->belongsTo(Penalty::class);
    }

    public function isLeave(): bool
    {
        return in_array($this->status, [
            self::STATUS_LEAVE_PAID,
            self::STATUS_LEAVE_SICK,
        ], true);
    }

    /**
     * Late beyond threshold ⇒ full day forfeit (Phase 4 applies the penalty row).
     */
    public function shouldForfeitDay(): bool
    {
        return (int) $this->late_minutes > self::LATE_FORFEIT_MINUTES;
    }
}
