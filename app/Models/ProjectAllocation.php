<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectAllocation extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'expenses_pool_usd' => 0,
        'payroll_pool_usd' => 0,
        'retention_pool_usd' => 0,
        'penalty_pool_usd' => 0,
        'profit_pool_usd' => 0,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'expenses_pool_usd',
        'payroll_pool_usd',
        'retention_pool_usd',
        'penalty_pool_usd',
        'profit_pool_usd',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expenses_pool_usd' => 'decimal:2',
            'payroll_pool_usd' => 'decimal:2',
            'retention_pool_usd' => 'decimal:2',
            'penalty_pool_usd' => 'decimal:2',
            'profit_pool_usd' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
