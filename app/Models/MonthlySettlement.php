<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlySettlement extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'vault_id',
        'year_month',
        'project_id',
        'project_scope_key',
        'money_received_iqd',
        'available_vault_balance_iqd',
        'project_expenses_iqd',
        'payroll_iqd',
        'employee_advances_iqd',
        'insurance_iqd',
        'penalties_iqd',
        'other_expenses_iqd',
        'approved_payments_iqd',
        'available_money_for_payment_iqd',
        'current_vault_iqd',
        'pending_commitments_iqd',
        'reserved_insurance_iqd',
        'payload',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::saving(function (MonthlySettlement $settlement): void {
            $settlement->project_scope_key = $settlement->project_id === null
                ? 0
                : (int) $settlement->project_id;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'project_scope_key' => 'integer',
            'money_received_iqd' => 'decimal:2',
            'available_vault_balance_iqd' => 'decimal:2',
            'project_expenses_iqd' => 'decimal:2',
            'payroll_iqd' => 'decimal:2',
            'employee_advances_iqd' => 'decimal:2',
            'insurance_iqd' => 'decimal:2',
            'penalties_iqd' => 'decimal:2',
            'other_expenses_iqd' => 'decimal:2',
            'approved_payments_iqd' => 'decimal:2',
            'available_money_for_payment_iqd' => 'decimal:2',
            'current_vault_iqd' => 'decimal:2',
            'pending_commitments_iqd' => 'decimal:2',
            'reserved_insurance_iqd' => 'decimal:2',
            'payload' => 'array',
        ];
    }

    public function vault(): BelongsTo
    {
        return $this->belongsTo(Vault::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
