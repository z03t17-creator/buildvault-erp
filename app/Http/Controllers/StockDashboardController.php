<?php

namespace App\Http\Controllers;

use App\Models\StockItem;
use App\Services\StockService;
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
            ->get(['id', 'category', 'quantity', 'purchase_price_iqd'])
            ->groupBy(function (StockItem $item) {
                $cat = trim((string) ($item->category ?? ''));

                return $cat !== '' ? $cat : 'uncategorized';
            })
            ->map(fn ($items, $category) => [
                'category' => $category,
                'items_count' => $items->count(),
                'quantity_total' => round((float) $items->sum(fn (StockItem $i) => (float) $i->quantity), 3),
                'value_iqd' => round((float) $items->sum(fn (StockItem $i) => $i->stockValueIqd()), 2),
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
            'recentMovements' => $summary['recent_movements'],
        ]);
    }
}
