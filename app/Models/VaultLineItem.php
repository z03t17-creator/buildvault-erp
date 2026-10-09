<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Line item on a unit-pay vault_line (qty × rate).
 */
class VaultLineItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'vault_line_id',
        'staff_rate_id',
        'item_name',
        'unit',
        'quantity',
        'unit_rate',
        'subtotal',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_rate' => 'decimal:4',
            'subtotal' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function vaultLine(): BelongsTo
    {
        return $this->belongsTo(VaultLine::class);
    }

    public function staffRate(): BelongsTo
    {
        return $this->belongsTo(StaffRate::class);
    }
}
