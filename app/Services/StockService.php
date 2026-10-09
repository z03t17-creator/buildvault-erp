<?php

namespace App\Services;

use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Stock inventory — IQD purchase prices; ledger movements with previous/new qty.
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
     *     recent_movements: \Illuminate\Support\Collection<int, StockMovement>,
     * }
     */
    public function dashboardSummary(?string $today = null): array
    {
        $today ??= now()->toDateString();

        $items = StockItem::query()->get(['id', 'quantity', 'min_quantity', 'purchase_price_iqd']);

        $stockValue = round($items->sum(fn (StockItem $i) => $i->stockValueIqd()), 2);
        $lowStock = $items->filter(fn (StockItem $i) => $i->isLowStock())->count();
        $outOfStock = $items->filter(fn (StockItem $i) => $i->isOutOfStock())->count();

        $todayIn = StockMovement::query()
            ->where('type', StockMovement::TYPE_IN)
            ->whereDate('moved_on', $today);
        $todayOut = StockMovement::query()
            ->where('type', StockMovement::TYPE_OUT)
            ->whereDate('moved_on', $today);

        $recent = StockMovement::query()
            ->with(['item:id,name,sku,unit', 'user:id,name', 'project:id,name'])
            ->orderByDesc('moved_on')
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        return [
            'total_items' => $items->count(),
            'stock_value_iqd' => $stockValue,
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
            'today_in_qty' => round((float) (clone $todayIn)->sum('quantity'), 3),
            'today_out_qty' => round((float) (clone $todayOut)->sum('quantity'), 3),
            'today_in_count' => (clone $todayIn)->count(),
            'today_out_count' => (clone $todayOut)->count(),
            'recent_movements' => $recent,
        ];
    }

    /**
     * Material cost for a project = DB rollup of OUT line values (qty × unit purchase price).
     */
    public function materialCostForProject(int $projectId): float
    {
        $total = StockMovement::query()
            ->where('project_id', $projectId)
            ->where('type', StockMovement::TYPE_OUT)
            ->selectRaw('COALESCE(SUM(quantity * COALESCE(purchase_price_iqd, 0)), 0) as total')
            ->value('total');

        return round((float) $total, 2);
    }

    /**
     * Recent stock-OUT materials attributed to a project (for Project Show).
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
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
            ])
            ->orderByDesc('moved_on')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (StockMovement $m) => [
                'id' => $m->id,
                'moved_on' => $m->moved_on?->toDateString(),
                'item_name' => $m->item?->name,
                'sku' => $m->item?->sku,
                'unit' => $m->item?->unit,
                'quantity' => round((float) $m->quantity, 3),
                'unit_price_iqd' => round((float) ($m->purchase_price_iqd ?? 0), 2),
                'line_value_iqd' => $m->lineValueIqd(),
                'previous_qty' => round((float) $m->previous_qty, 3),
                'new_qty' => round((float) $m->new_qty, 3),
                'tower' => $m->tower?->name,
                'floor' => $m->floor?->name,
                'purpose' => $m->purpose,
                'receiver' => $m->receiver,
            ])
            ->values();
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

            $unitPrice = array_key_exists('purchase_price_iqd', $data) && $data['purchase_price_iqd'] !== null && $data['purchase_price_iqd'] !== ''
                ? round((float) $data['purchase_price_iqd'], 2)
                : (float) $item->purchase_price_iqd;

            if ($unitPrice < 0) {
                throw new InvalidArgumentException('Purchase price cannot be negative.');
            }

            $previous = round((float) $item->quantity, 3);
            $newQty = round($previous + $qty, 3);

            $item->quantity = $newQty;
            if ($unitPrice > 0) {
                $item->purchase_price_iqd = $unitPrice;
            }
            if (! empty($data['supplier_id'])) {
                $item->supplier_id = (int) $data['supplier_id'];
            }
            $item->save();

            return StockMovement::query()->create([
                'type' => StockMovement::TYPE_IN,
                'stock_item_id' => $item->id,
                'quantity' => $qty,
                'moved_on' => $this->date($data['moved_on'] ?? now()->toDateString()),
                'supplier_id' => isset($data['supplier_id']) && $data['supplier_id'] !== ''
                    ? (int) $data['supplier_id']
                    : $item->supplier_id,
                'purchase_price_iqd' => $unitPrice,
                'project_id' => $this->nullableId($data['project_id'] ?? null),
                'tower_id' => null,
                'floor_id' => null,
                'invoice_ref' => $this->nullableString($data['invoice_ref'] ?? null),
                'receiver' => null,
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
            // Snapshot unit purchase price at issue time for stable project material cost.
            $unitPrice = (float) $item->purchase_price_iqd;

            $item->quantity = $newQty;
            $item->save();

            return StockMovement::query()->create([
                'type' => StockMovement::TYPE_OUT,
                'stock_item_id' => $item->id,
                'quantity' => $qty,
                'moved_on' => $this->date($data['moved_on'] ?? now()->toDateString()),
                'supplier_id' => null,
                'purchase_price_iqd' => $unitPrice,
                'project_id' => $projectId,
                'tower_id' => $this->nullableId($data['tower_id'] ?? null),
                'floor_id' => $this->nullableId($data['floor_id'] ?? null),
                'invoice_ref' => null,
                'receiver' => $this->nullableString($data['receiver'] ?? null),
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
