<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class StockItem extends Model
{
    /** @var list<string> */
    public const UNITS = [
        'Pcs',
        'M2',
        'Bag',
        'Meter',
        'دانە',
        'm²',
        'جوال',
        'مەتر',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'sku',
        'barcode',
        'category',
        'stock_category_id',
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

    public function stockCategory(): BelongsTo
    {
        return $this->belongsTo(StockCategory::class);
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

    public function stockStatus(): string
    {
        if ($this->isOutOfStock()) {
            return 'out';
        }
        if ($this->isLowStock()) {
            return 'low';
        }

        return 'ok';
    }

    public function stockValueIqd(): float
    {
        return round((float) $this->quantity * (float) $this->purchase_price_iqd, 2);
    }

    /** Average unit cost — stored in purchase_price_iqd after weighted receive updates. */
    public function averageUnitCost(): float
    {
        return round((float) $this->purchase_price_iqd, 2);
    }

    public static function generateSku(?string $categoryName = null): string
    {
        $prefix = 'BV';
        if ($categoryName) {
            $slug = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]+/', '', $categoryName) ?: 'GEN', 0, 3));
            $prefix .= '-'.$slug;
        }

        do {
            $sku = $prefix.'-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (static::query()->where('sku', $sku)->exists());

        return $sku;
    }

    public static function generateBarcode(?string $sku = null): string
    {
        $base = $sku ?: static::generateSku();
        $barcode = $base;
        $i = 0;
        while (static::query()->where('barcode', $barcode)->exists()) {
            $i++;
            $barcode = $base.'-'.$i;
        }

        return $barcode;
    }
}
