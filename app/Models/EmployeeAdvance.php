<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAdvance extends Model
{
    use SoftDeletes;

    public const STATUS_OPEN = 'open';

    public const STATUS_REPAID = 'repaid';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_REPAID,
        self::STATUS_CANCELLED,
    ];

    public const REPAY_PAYROLL = 'payroll_deduction';

    public const REPAY_CASH = 'cash';

    public const REPAY_BANK = 'bank_transfer';

    public const REPAY_OTHER = 'other';

    /** @var list<string> */
    public const REPAYMENT_METHODS = [
        self::REPAY_PAYROLL,
        self::REPAY_CASH,
        self::REPAY_BANK,
        self::REPAY_OTHER,
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_OPEN,
        'repayment_method' => self::REPAY_PAYROLL,
        'amount_usd' => 0,
        'remaining_usd' => 0,
        'currency' => 'IQD',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'worker_id',
        'project_id',
        'amount_iqd',
        'remaining_iqd',
        'amount_usd',
        'remaining_usd',
        'currency',
        'advanced_on',
        'reason',
        'repayment_method',
        'notes',
        'status',
        'entered_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_iqd' => 'decimal:2',
            'remaining_iqd' => 'decimal:2',
            'amount_usd' => 'decimal:2',
            'remaining_usd' => 'decimal:2',
            'advanced_on' => 'date',
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

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
