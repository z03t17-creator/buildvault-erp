<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Daily / monthly staff attendance desk.
 * Late > 30m or absent → forfeit_day (+ penalty when project is set).
 */
class Attendance extends Model
{
    use SoftDeletes;

    public const STATUS_PRESENT = 'present';

    public const STATUS_LATE = 'late';

    public const STATUS_ABSENT_UNEXCUSED = 'absent_unexcused';

    public const STATUS_LEAVE_PAID = 'leave_paid';

    public const STATUS_LEAVE_SICK = 'leave_sick';

    /** Half-day / short leave — counts as 0.5 wage day. */
    public const STATUS_HALF_DAY = 'half_day';

    /** Late threshold (minutes) that triggers a full-day salary forfeit. */
    public const LATE_FORFEIT_MINUTES = 30;

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PRESENT,
        self::STATUS_LATE,
        self::STATUS_ABSENT_UNEXCUSED,
        self::STATUS_LEAVE_PAID,
        self::STATUS_LEAVE_SICK,
        self::STATUS_HALF_DAY,
    ];

    /** Statuses that count toward worked / payable time. */
    public const WORKED_STATUSES = [
        self::STATUS_PRESENT,
        self::STATUS_LATE,
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
        'staff_id',
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

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /** @deprecated Use staff() — kept during UI transition. */
    public function worker(): BelongsTo
    {
        return $this->staff();
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
            self::STATUS_HALF_DAY,
        ], true);
    }

    public function isHalfDay(): bool
    {
        return $this->status === self::STATUS_HALF_DAY;
    }

    public function isAbsent(): bool
    {
        return $this->status === self::STATUS_ABSENT_UNEXCUSED;
    }

    public function isWorked(): bool
    {
        return in_array($this->status, self::WORKED_STATUSES, true);
    }

    /**
     * Wage day factor for estimates / payroll (1, 0.5, or 0).
     */
    public function wageDayFactor(): float
    {
        if ($this->isWorked()) {
            return $this->forfeit_day ? 0.0 : 1.0;
        }
        if ($this->isHalfDay()) {
            return 0.5;
        }

        return 0.0;
    }

    /**
     * Late beyond threshold ⇒ full day forfeit (Phase 4 applies the penalty row).
     */
    public function shouldForfeitDay(): bool
    {
        if ($this->isAbsent()) {
            return true;
        }

        return (int) $this->late_minutes > self::LATE_FORFEIT_MINUTES;
    }
}
