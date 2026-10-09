<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-person price list row for unit-pay staff.
 */
class StaffRate extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'staff_id',
        'item_name',
        'unit',
        'rate',
        'currency',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'sort_order' => 'integer',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
