<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public const TYPE_IN = 'in';

    public const TYPE_OUT = 'out';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_IN,
        self::TYPE_OUT,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'stock_item_id',
        'quantity',
        'moved_on',
        'supplier_id',
        'purchase_price_iqd',
        'project_id',
        'tower_id',
        'floor_id',
        'invoice_ref',
        'receiver',
        'issuer',
        'purpose',
        'reference',
        'previous_qty',
        'new_qty',
        'user_id',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'purchase_price_iqd' => 'decimal:2',
            'previous_qty' => 'decimal:3',
            'new_qty' => 'decimal:3',
            'moved_on' => 'date',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tower(): BelongsTo
    {
        return $this->belongsTo(Tower::class);
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isIn(): bool
    {
        return $this->type === self::TYPE_IN;
    }

    public function isOut(): bool
    {
        return $this->type === self::TYPE_OUT;
    }

    /**
     * Line value for material cost (OUT) or purchase value (IN).
     */
    public function lineValueIqd(): float
    {
        $unit = (float) ($this->purchase_price_iqd ?? 0);

        return round((float) $this->quantity * $unit, 2);
    }
}
