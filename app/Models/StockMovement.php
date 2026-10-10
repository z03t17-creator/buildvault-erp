<?php

namespace App\Models;

use App\Support\DualCurrency;
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

    public const SITE_VILLA = 'villa';

    public const SITE_BUILDING = 'building';

    /** @var list<string> */
    public const SITE_KINDS = [
        self::SITE_VILLA,
        self::SITE_BUILDING,
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
        'currency',
        'purchase_price_iqd',
        'purchase_price_usd',
        'total_cost_iqd',
        'total_cost_usd',
        'project_id',
        'tower_id',
        'floor_id',
        'site_kind',
        'block',
        'zone',
        'floor_label',
        'apartment_number',
        'villa_number',
        'invoice_ref',
        'shelf_zone',
        'receiver',
        'staff_id',
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
            'purchase_price_usd' => 'decimal:2',
            'total_cost_iqd' => 'decimal:2',
            'total_cost_usd' => 'decimal:2',
            'previous_qty' => 'decimal:3',
            'new_qty' => 'decimal:3',
            'moved_on' => 'date',
        ];
    }

    public function costCurrency(): string
    {
        $currency = strtoupper((string) ($this->currency ?: DualCurrency::IQD));

        return in_array($currency, DualCurrency::CURRENCIES, true)
            ? $currency
            : DualCurrency::IQD;
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

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class)->withTrashed();
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

    public function lineValueIqd(): float
    {
        if ($this->total_cost_iqd !== null) {
            return round((float) $this->total_cost_iqd, 2);
        }

        $unit = (float) ($this->purchase_price_iqd ?? 0);

        return round((float) $this->quantity * $unit, 2);
    }

    public function placeLabel(): string
    {
        if ($this->site_kind === self::SITE_VILLA) {
            return collect(['Villa', $this->villa_number, $this->zone])
                ->filter()
                ->implode(' · ');
        }

        if ($this->site_kind === self::SITE_BUILDING) {
            return collect([
                'Building',
                $this->block,
                $this->zone,
                $this->floor_label ?: $this->floor?->name,
                $this->apartment_number,
            ])->filter()->implode(' · ');
        }

        return collect([$this->tower?->name, $this->floor?->name])->filter()->implode(' · ');
    }
}
