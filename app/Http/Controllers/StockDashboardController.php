<?php

namespace App\Http\Controllers;

use App\Models\StockCategory;
use App\Models\StockItem;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StockDashboardController extends Controller
{
    public function __construct(
        private readonly StockService $stock,
    ) {}

    public function __invoke(Request $request): Response
    {
        $this->authorize('viewAny', StockItem::class);

        $q = trim((string) $request->get('q', ''));
        $status = (string) $request->get('status', '');

        $summary = $this->stock->dashboardSummary();

        $itemsQuery = StockItem::query()
            ->with('stockCategory:id,name')
            ->orderBy('name');

        if ($q !== '') {
            $itemsQuery->where(function ($builder) use ($q) {
                $builder->where('name', 'like', '%'.$q.'%')
                    ->orWhere('sku', 'like', '%'.$q.'%')
                    ->orWhere('barcode', 'like', '%'.$q.'%');
            });
        }

        $items = $itemsQuery->get()->map(function (StockItem $item) {
            $item->setAttribute('average_unit_cost', $item->averageUnitCost());
            $item->setAttribute('cost_currency', $item->costCurrency());
            $item->setAttribute('stock_value', $item->stockValue());
            $item->setAttribute('is_low_stock', $item->isLowStock());
            $item->setAttribute('is_out_of_stock', $item->isOutOfStock());
            $item->setAttribute('stock_status', $item->stockStatus());
            $item->setAttribute(
                'category_label',
                $item->stockCategory?->name ?: (trim((string) ($item->category ?? '')) ?: null)
            );

            return $item;
        });

        if (in_array($status, ['ok', 'low', 'out'], true)) {
            $items = $items->filter(fn (StockItem $item) => $item->stock_status === $status)->values();
        }

        return Inertia::render('Stock/Dashboard', [
            'summary' => [
                'total_items' => $summary['total_items'],
                'stock_value_iqd' => $summary['stock_value_iqd'],
                'stock_value_usd' => $summary['stock_value_usd'],
                'low_stock' => $summary['low_stock'],
                'out_of_stock' => $summary['out_of_stock'],
                'today_movements' => $summary['today_movements'],
                'today_in_count' => $summary['today_in_count'],
                'today_out_count' => $summary['today_out_count'],
            ],
            'items' => $items,
            'filters' => [
                'q' => $q,
                'status' => in_array($status, ['ok', 'low', 'out'], true) ? $status : '',
            ],
            'categories' => StockCategory::query()->orderBy('name')->get(['id', 'name']),
            'lowStockItems' => $summary['low_stock_items']->map(fn (StockItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'barcode' => $item->barcode,
                'unit' => $item->unit,
                'quantity' => (float) $item->quantity,
                'is_low_stock' => true,
                'is_out_of_stock' => $item->isOutOfStock(),
            ])->values(),
            'recentMovements' => $summary['recent_movements']->take(8)->map(fn ($m) => [
                'id' => $m->id,
                'type' => $m->type,
                'moved_on' => $m->moved_on?->toDateString(),
                'quantity' => (float) $m->quantity,
                'item' => $m->item ? [
                    'id' => $m->item->id,
                    'name' => $m->item->name,
                    'unit' => $m->item->unit,
                ] : null,
                'place' => $m->placeLabel(),
            ])->values(),
            'canManage' => Gate::allows('create', StockItem::class),
        ]);
    }
}
