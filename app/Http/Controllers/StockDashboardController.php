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
            ],
            'recentMovements' => $summary['recent_movements'],
        ]);
    }
}
