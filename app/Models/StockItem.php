<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'sku',
        'category',
        'unit',
        'quantity',
        'min_quantity',
        'purchase_price_iqd',
        'supplier_id',
        'location',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'min_quantity' => 'decimal:3',
            'purchase_price_iqd' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isLowStock(): bool
    {
        $qty = (float) $this->quantity;
        $min = (float) $this->min_quantity;

        return $qty > 0 && $min > 0 && $qty <= $min;
    }

    public function isOutOfStock(): bool
    {
        return (float) $this->quantity <= 0;
    }

    public function stockValueIqd(): float
    {
        return round((float) $this->quantity * (float) $this->purchase_price_iqd, 2);
    }
}
