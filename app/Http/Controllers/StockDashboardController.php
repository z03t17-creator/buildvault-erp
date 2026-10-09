<?php

namespace App\Http\Controllers;

use App\Models\StockItem;
use App\Services\StockService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StockDashboardController extends Controller
{
    public function __construct(
        private readonly StockService $stock,
    ) {}

    public function __invoke(): Response
    {
        $this->authorize('viewAny', StockItem::class);

        $summary = $this->stock->dashboardSummary();
        $byCategory = StockItem::query()
            ->with('stockCategory:id,name')
            ->get()
            ->groupBy(function (StockItem $item) {
                $cat = trim((string) ($item->stockCategory?->name ?: $item->category ?: ''));

                return $cat !== '' ? $cat : 'uncategorized';
            })
            ->map(fn ($items, $category) => [
                'category' => $category,
                'items_count' => $items->count(),
                'quantity_total' => round((float) $items->sum(fn (StockItem $i) => (float) $i->quantity), 3),
                'value_iqd' => round((float) $items->sum(fn (StockItem $i) => $i->stockValueIqd()), 2),
                'low_stock' => $items->filter(fn (StockItem $i) => $i->isLowStock())->count(),
            ])
            ->sortBy('category')
            ->values()
            ->all();

        return Inertia::render('Stock/Dashboard', [
            'summary' => [
                'total_items' => $summary['total_items'],
                'stock_value_iqd' => $summary['stock_value_iqd'],
                'low_stock' => $summary['low_stock'],
                'out_of_stock' => $summary['out_of_stock'],
                'today_in_qty' => $summary['today_in_qty'],
                'today_out_qty' => $summary['today_out_qty'],
                'today_in_count' => $summary['today_in_count'],
                'today_out_count' => $summary['today_out_count'],
                'by_category' => $byCategory,
            ],
            'lowStockItems' => $summary['low_stock_items']->map(fn (StockItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'barcode' => $item->barcode,
                'unit' => $item->unit,
                'quantity' => (float) $item->quantity,
                'min_quantity' => (float) $item->min_quantity,
                'is_low_stock' => true,
                'is_out_of_stock' => $item->isOutOfStock(),
            ])->values(),
            'recentMovements' => $summary['recent_movements']->map(fn ($m) => [
                'id' => $m->id,
                'type' => $m->type,
                'moved_on' => $m->moved_on?->toDateString(),
                'quantity' => (float) $m->quantity,
                'item' => $m->item ? [
                    'id' => $m->item->id,
                    'name' => $m->item->name,
                    'sku' => $m->item->sku,
                    'unit' => $m->item->unit,
                ] : null,
                'project' => $m->project ? ['id' => $m->project->id, 'name' => $m->project->name] : null,
                'staff' => $m->staff ? ['id' => $m->staff->id, 'name' => $m->staff->name] : null,
                'place' => $m->placeLabel(),
            ])->values(),
            'canManage' => Gate::allows('create', StockItem::class),
        ]);
    }
}
