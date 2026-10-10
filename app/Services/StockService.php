<?php

namespace App\Services;

use App\Models\ApartmentUnit;
use App\Models\BuildingBlock;
use App\Models\Staff;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\DualCurrency;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Stock inventory — dual-currency unit costs (USD or IQD, never blended).
 * Stock OUT never allows negative on-hand quantity and requires a project
 * so material cost rolls up into ProjectFinancialService.
 *
 * Stock IN only updates inventory — it does not create Expense rows or vault
 * withdrawals. Cash/site purchases go through the Expenses module separately
 * to avoid double-counting against project financials.
 */
class StockService
{
    /**
     * @return array{
     *     total_items: int,
     *     stock_value_iqd: float,
     *     low_stock: int,
     *     out_of_stock: int,
     *     today_in_qty: float,
     *     today_out_qty: float,
     *     today_in_count: int,
     *     today_out_count: int,
     *     recent_movements: Collection<int, StockMovement>,
     *     low_stock_items: Collection<int, StockItem>,
     * }
     */
    public function dashboardSummary(?string $today = null): array
    {
        $today ??= now()->toDateString();

        $items = StockItem::query()
            ->with('stockCategory:id,name')
            ->get();

        $stockValueIqd = round($items->sum(fn (StockItem $i) => $i->stockValueIqd()), 2);
        $stockValueUsd = round($items->sum(fn (StockItem $i) => $i->stockValueUsd()), 2);
        $lowStockItems = $items->filter(fn (StockItem $i) => $i->isLowStock())->values();
        $outOfStock = $items->filter(fn (StockItem $i) => $i->isOutOfStock())->count();

        $todayIn = StockMovement::query()
            ->where('type', StockMovement::TYPE_IN)
            ->whereDate('moved_on', $today);
        $todayOut = StockMovement::query()
            ->where('type', StockMovement::TYPE_OUT)
            ->whereDate('moved_on', $today);

        $recent = StockMovement::query()
            ->with([
                'item:id,name,sku,barcode,unit',
                'user:id,name',
                'project:id,name',
                'staff:id,name',
            ])
            ->orderByDesc('moved_on')
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        return [
            'total_items' => $items->count(),
            'stock_value_iqd' => $stockValueIqd,
            'stock_value_usd' => $stockValueUsd,
            'low_stock' => $lowStockItems->count(),
            'out_of_stock' => $outOfStock,
            'today_in_qty' => round((float) (clone $todayIn)->sum('quantity'), 3),
            'today_out_qty' => round((float) (clone $todayOut)->sum('quantity'), 3),
            'today_in_count' => (clone $todayIn)->count(),
            'today_out_count' => (clone $todayOut)->count(),
            'recent_movements' => $recent,
            'low_stock_items' => $lowStockItems->take(8)->values(),
        ];
    }

    public function materialCostForProject(int $projectId): float
    {
        $total = StockMovement::query()
            ->where('project_id', $projectId)
            ->where('type', StockMovement::TYPE_OUT)
            ->selectRaw('COALESCE(SUM(COALESCE(total_cost_iqd, quantity * COALESCE(purchase_price_iqd, 0))), 0) as total')
            ->value('total');

        return round((float) $total, 2);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function recentMaterialsForProject(int $projectId, int $limit = 12)
    {
        return StockMovement::query()
            ->where('project_id', $projectId)
            ->where('type', StockMovement::TYPE_OUT)
            ->with([
                'item:id,name,sku,unit',
                'tower:id,name',
                'floor:id,name',
                'staff:id,name',
            ])
            ->orderByDesc('moved_on')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (StockMovement $m) {
                $currency = $m->costCurrency();
                $unit = $currency === DualCurrency::USD
                    ? round((float) ($m->purchase_price_usd ?? 0), 2)
                    : round((float) ($m->purchase_price_iqd ?? 0), 2);
                $line = $currency === DualCurrency::USD
                    ? round((float) ($m->total_cost_usd ?? ($m->quantity * $unit)), 2)
                    : $m->lineValueIqd();

                return [
                    'id' => $m->id,
                    'moved_on' => $m->moved_on?->toDateString(),
                    'item_name' => $m->item?->name,
                    'sku' => $m->item?->sku,
                    'unit' => $m->item?->unit,
                    'quantity' => round((float) $m->quantity, 3),
                    'currency' => $currency,
                    'unit_price' => $unit,
                    'unit_price_iqd' => round((float) ($m->purchase_price_iqd ?? 0), 2),
                    'line_value' => $line,
                    'line_value_iqd' => $m->lineValueIqd(),
                    'previous_qty' => round((float) $m->previous_qty, 3),
                    'new_qty' => round((float) $m->new_qty, 3),
                    'tower' => $m->tower?->name,
                    'floor' => $m->floor?->name,
                    'place' => $m->placeLabel(),
                    'purpose' => $m->purpose,
                    'receiver' => $m->receiver ?: $m->staff?->name,
                ];
            })
            ->values();
    }

    /**
     * Material used per villa / apartment unit for the consumption report.
     *
     * @return list<array<string, mixed>>
     */
    public function consumptionByPlace(?int $projectId = null): array
    {
        $query = StockMovement::query()
            ->where('type', StockMovement::TYPE_OUT)
            ->with(['item:id,name,sku,unit,stock_category_id', 'item.stockCategory:id,name', 'project:id,name', 'staff:id,name'])
            ->orderByDesc('moved_on')
            ->orderByDesc('id');

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $groups = [];
        foreach ($query->get() as $movement) {
            $placeKey = $this->placeKey($movement);
            if (! isset($groups[$placeKey])) {
                $groups[$placeKey] = [
                    'key' => $placeKey,
                    'project_id' => $movement->project_id,
                    'project_name' => $movement->project?->name,
                    'site_kind' => $movement->site_kind,
                    'place_label' => $movement->placeLabel() ?: ($movement->project?->name ?: '—'),
                    'block' => $movement->block,
                    'zone' => $movement->zone,
                    'floor_label' => $movement->floor_label,
                    'apartment_number' => $movement->apartment_number,
                    'villa_number' => $movement->villa_number,
                    'lines' => [],
                    'total_qty' => 0.0,
                    'total_cost_iqd' => 0.0,
                ];
            }

            $qty = round((float) $movement->quantity, 3);
            $cost = $movement->lineValueIqd();
            $groups[$placeKey]['lines'][] = [
                'id' => $movement->id,
                'moved_on' => $movement->moved_on?->toDateString(),
                'item_name' => $movement->item?->name,
                'sku' => $movement->item?->sku,
                'category' => $movement->item?->stockCategory?->name ?: $movement->item?->category,
                'unit' => $movement->item?->unit,
                'quantity' => $qty,
                'unit_cost_iqd' => round((float) ($movement->purchase_price_iqd ?? 0), 2),
                'line_cost_iqd' => $cost,
                'receiver' => $movement->receiver ?: $movement->staff?->name,
            ];
            $groups[$placeKey]['total_qty'] = round($groups[$placeKey]['total_qty'] + $qty, 3);
            $groups[$placeKey]['total_cost_iqd'] = round($groups[$placeKey]['total_cost_iqd'] + $cost, 2);
        }

        return array_values($groups);
    }

    /**
     * Cascading place suggestions for dispatch forms.
     *
     * @return list<array<string, mixed>>
     */
    public function placeSuggestions(?int $projectId = null): array
    {
        $rows = [];

        $movements = StockMovement::query()
            ->where('type', StockMovement::TYPE_OUT)
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->whereNotNull('site_kind')
            ->orderByDesc('id')
            ->limit(200)
            ->get([
                'project_id', 'site_kind', 'block', 'zone', 'floor_label',
                'apartment_number', 'villa_number',
            ]);

        foreach ($movements as $row) {
            $rows[] = [
                'project_id' => $row->project_id,
                'site_kind' => $row->site_kind,
                'block' => $row->block,
                'zone' => $row->zone,
                'floor' => $row->floor_label,
                'apartment_number' => $row->apartment_number,
                'villa_number' => $row->villa_number,
            ];
        }

        $blocks = BuildingBlock::query()
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->with(['apartmentUnits:id,building_block_id,unit_label,floor_number,category'])
            ->get();

        foreach ($blocks as $block) {
            $blockLabel = $block->code ?: $block->name;
            foreach ($block->apartmentUnits as $unit) {
                $rows[] = [
                    'project_id' => $block->project_id,
                    'site_kind' => StockMovement::SITE_BUILDING,
                    'block' => $blockLabel,
                    'zone' => null,
                    'floor' => $unit->floor_number !== null ? (string) $unit->floor_number : null,
                    'apartment_number' => $unit->unit_label,
                    'villa_number' => null,
                ];
            }
            if ($block->apartmentUnits->isEmpty()) {
                $rows[] = [
                    'project_id' => $block->project_id,
                    'site_kind' => StockMovement::SITE_BUILDING,
                    'block' => $blockLabel,
                    'zone' => null,
                    'floor' => null,
                    'apartment_number' => null,
                    'villa_number' => null,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function stockIn(array $data, ?User $actor = null): StockMovement
    {
        return DB::transaction(function () use ($data, $actor) {
            /** @var StockItem $item */
            $item = StockItem::query()->lockForUpdate()->findOrFail((int) $data['stock_item_id']);

            $qty = round((float) ($data['quantity'] ?? 0), 3);
            if ($qty <= 0) {
                throw new InvalidArgumentException('Stock-in quantity must be greater than zero.');
            }

            $currency = $item->costCurrency();
            if (! empty($data['currency'])) {
                $incoming = strtoupper((string) $data['currency']);
                if (in_array($incoming, DualCurrency::CURRENCIES, true) && $incoming !== $currency) {
                    throw new InvalidArgumentException(
                        'Stock-in currency must match the item currency ('.$currency.').'
                    );
                }
            }

            $unitPrice = $this->resolveUnitPrice($data, $item);

            if ($unitPrice < 0) {
                throw new InvalidArgumentException('Purchase price cannot be negative.');
            }

            $previous = round((float) $item->quantity, 3);
            $newQty = round($previous + $qty, 3);
            $totalCost = round($qty * $unitPrice, 2);

            // Weighted average unit cost in the item's currency only.
            if ($newQty > 0) {
                $prevValue = round($previous * $item->unitCost(), 2);
                $avg = round(($prevValue + $totalCost) / $newQty, 2);
                $this->setItemUnitCost($item, $currency, $avg);
            } elseif ($unitPrice > 0) {
                $this->setItemUnitCost($item, $currency, $unitPrice);
            }

            $item->quantity = $newQty;
            if (! empty($data['supplier_id'])) {
                $item->supplier_id = (int) $data['supplier_id'];
            }
            $shelf = $this->nullableString($data['shelf_zone'] ?? ($data['location'] ?? null));
            if ($shelf) {
                $item->location = $shelf;
            }
            $item->save();

            $legs = $this->costLegs($currency, $unitPrice, $totalCost);

            return StockMovement::query()->create([
                'type' => StockMovement::TYPE_IN,
                'stock_item_id' => $item->id,
                'quantity' => $qty,
                'moved_on' => $this->date($data['moved_on'] ?? now()->toDateString()),
                'supplier_id' => isset($data['supplier_id']) && $data['supplier_id'] !== ''
                    ? (int) $data['supplier_id']
                    : $item->supplier_id,
                'currency' => $currency,
                'purchase_price_usd' => $legs['unit_usd'],
                'purchase_price_iqd' => $legs['unit_iqd'],
                'total_cost_usd' => $legs['total_usd'],
                'total_cost_iqd' => $legs['total_iqd'],
                'project_id' => $this->nullableId($data['project_id'] ?? null),
                'tower_id' => null,
                'floor_id' => null,
                'invoice_ref' => $this->nullableString($data['invoice_ref'] ?? null),
                'shelf_zone' => $shelf,
                'receiver' => null,
                'staff_id' => null,
                'issuer' => null,
                'purpose' => null,
                'reference' => $this->nullableString($data['reference'] ?? ($data['invoice_ref'] ?? null)),
                'previous_qty' => $previous,
                'new_qty' => $newQty,
                'user_id' => $actor?->id ?? ($data['user_id'] ?? null),
                'notes' => $this->nullableString($data['notes'] ?? null),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function stockOut(array $data, ?User $actor = null): StockMovement
    {
        return DB::transaction(function () use ($data, $actor) {
            $projectId = $this->nullableId($data['project_id'] ?? null);
            if ($projectId === null) {
                throw new InvalidArgumentException(
                    'Stock-out requires a project so material cost can be attributed.'
                );
            }

            /** @var StockItem $item */
            $item = StockItem::query()->lockForUpdate()->findOrFail((int) $data['stock_item_id']);

            $qty = round((float) ($data['quantity'] ?? 0), 3);
            if ($qty <= 0) {
                throw new InvalidArgumentException('Stock-out quantity must be greater than zero.');
            }

            $previous = round((float) $item->quantity, 3);
            if ($qty > $previous) {
                throw new InvalidArgumentException(
                    'Insufficient stock: cannot go negative (on hand '.$previous.', requested '.$qty.').'
                );
            }

            $newQty = round($previous - $qty, 3);
            $currency = $item->costCurrency();
            $unitPrice = $item->unitCost();
            $totalCost = round($qty * $unitPrice, 2);
            $legs = $this->costLegs($currency, $unitPrice, $totalCost);

            $item->quantity = $newQty;
            $item->save();

            $staffId = $this->nullableId($data['staff_id'] ?? null);
            $receiver = $this->nullableString($data['receiver'] ?? null);
            if ($staffId && ! $receiver) {
                $receiver = Staff::query()->whereKey($staffId)->value('name');
            }

            return StockMovement::query()->create([
                'type' => StockMovement::TYPE_OUT,
                'stock_item_id' => $item->id,
                'quantity' => $qty,
                'moved_on' => $this->date($data['moved_on'] ?? now()->toDateString()),
                'supplier_id' => null,
                'currency' => $currency,
                'purchase_price_usd' => $legs['unit_usd'],
                'purchase_price_iqd' => $legs['unit_iqd'],
                'total_cost_usd' => $legs['total_usd'],
                'total_cost_iqd' => $legs['total_iqd'],
                'project_id' => $projectId,
                'tower_id' => $this->nullableId($data['tower_id'] ?? null),
                'floor_id' => $this->nullableId($data['floor_id'] ?? null),
                'site_kind' => $this->nullableString($data['site_kind'] ?? null),
                'block' => $this->nullableString($data['block'] ?? null),
                'zone' => $this->nullableString($data['zone'] ?? null),
                'floor_label' => $this->nullableString($data['floor_label'] ?? ($data['floor'] ?? null)),
                'apartment_number' => $this->nullableString($data['apartment_number'] ?? null),
                'villa_number' => $this->nullableString($data['villa_number'] ?? null),
                'invoice_ref' => null,
                'shelf_zone' => null,
                'receiver' => $receiver,
                'staff_id' => $staffId,
                'issuer' => $this->nullableString($data['issuer'] ?? null),
                'purpose' => $this->nullableString($data['purpose'] ?? null),
                'reference' => $this->nullableString($data['reference'] ?? null),
                'previous_qty' => $previous,
                'new_qty' => $newQty,
                'user_id' => $actor?->id ?? ($data['user_id'] ?? null),
                'notes' => $this->nullableString($data['notes'] ?? null),
            ]);
        });
    }

    private function placeKey(StockMovement $movement): string
    {
        return implode('|', [
            (string) ($movement->project_id ?? ''),
            (string) ($movement->site_kind ?? ''),
            (string) ($movement->villa_number ?? ''),
            (string) ($movement->block ?? ''),
            (string) ($movement->zone ?? ''),
            (string) ($movement->floor_label ?? ''),
            (string) ($movement->apartment_number ?? ''),
            (string) ($movement->tower_id ?? ''),
            (string) ($movement->floor_id ?? ''),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveUnitPrice(array $data, StockItem $item): float
    {
        if (array_key_exists('purchase_price', $data) && $data['purchase_price'] !== null && $data['purchase_price'] !== '') {
            return round((float) $data['purchase_price'], 2);
        }

        $currency = $item->costCurrency();
        if ($currency === DualCurrency::USD) {
            if (array_key_exists('purchase_price_usd', $data) && $data['purchase_price_usd'] !== null && $data['purchase_price_usd'] !== '') {
                return round((float) $data['purchase_price_usd'], 2);
            }

            return round((float) $item->purchase_price_usd, 2);
        }

        if (array_key_exists('purchase_price_iqd', $data) && $data['purchase_price_iqd'] !== null && $data['purchase_price_iqd'] !== '') {
            return round((float) $data['purchase_price_iqd'], 2);
        }

        return round((float) $item->purchase_price_iqd, 2);
    }

    private function setItemUnitCost(StockItem $item, string $currency, float $unitPrice): void
    {
        $item->currency = $currency;
        if ($currency === DualCurrency::USD) {
            $item->purchase_price_usd = $unitPrice;
            $item->purchase_price_iqd = 0;
        } else {
            $item->purchase_price_iqd = $unitPrice;
            $item->purchase_price_usd = 0;
        }
    }

    /**
     * @return array{unit_usd: float, unit_iqd: float, total_usd: float, total_iqd: float}
     */
    private function costLegs(string $currency, float $unitPrice, float $totalCost): array
    {
        if ($currency === DualCurrency::USD) {
            return [
                'unit_usd' => $unitPrice,
                'unit_iqd' => 0.0,
                'total_usd' => $totalCost,
                'total_iqd' => 0.0,
            ];
        }

        return [
            'unit_usd' => 0.0,
            'unit_iqd' => $unitPrice,
            'total_usd' => 0.0,
            'total_iqd' => $totalCost,
        ];
    }

    private function date(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }

    private function nullableId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
