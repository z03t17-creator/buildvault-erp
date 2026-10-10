<?php

namespace App\Http\Controllers;

use App\Http\Requests\Stock\StoreStockInRequest;
use App\Http\Requests\Stock\StoreStockOutRequest;
use App\Models\Floor;
use App\Models\Project;
use App\Models\Staff;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Tower;
use App\Services\StockService;
use App\Support\DualCurrency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Inertia\Inertia;
use Inertia\Response;

class StockMovementController extends Controller
{
    public function __construct(
        private readonly StockService $stock,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', StockMovement::class);

        $type = $request->get('type');
        $itemId = $request->integer('stock_item_id') ?: null;
        $projectId = $request->integer('project_id') ?: null;

        $query = StockMovement::query()
            ->with([
                'item:id,name,sku,unit',
                'supplier:id,name',
                'project:id,name',
                'tower:id,name',
                'floor:id,name',
                'staff:id,name',
                'user:id,name',
            ])
            ->orderByDesc('moved_on')
            ->orderByDesc('id');

        if (in_array($type, StockMovement::TYPES, true)) {
            $query->where('type', $type);
        }
        if ($itemId) {
            $query->where('stock_item_id', $itemId);
        }
        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $movements = $query->limit(200)->get()->map(function (StockMovement $m) {
            $m->setAttribute('place_label', $m->placeLabel());
            $m->setAttribute('line_value_iqd', $m->lineValueIqd());

            return $m;
        });

        $overview = [
            'movements' => $movements->count(),
            'in_count' => $movements->where('type', StockMovement::TYPE_IN)->count(),
            'out_count' => $movements->where('type', StockMovement::TYPE_OUT)->count(),
            'in_qty' => round((float) $movements->where('type', StockMovement::TYPE_IN)->sum('quantity'), 3),
            'out_qty' => round((float) $movements->where('type', StockMovement::TYPE_OUT)->sum('quantity'), 3),
        ];

        return Inertia::render('Stock/Movements/Index', [
            'movements' => $movements,
            'filters' => [
                'type' => in_array($type, StockMovement::TYPES, true) ? $type : '',
                'stock_item_id' => $itemId,
                'project_id' => $projectId,
            ],
            'items' => StockItem::query()->orderBy('name')->get(['id', 'name', 'sku']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'overview' => $overview,
        ]);
    }

    public function consumption(Request $request): Response
    {
        $this->authorize('viewAny', StockMovement::class);

        $projectId = $request->integer('project_id') ?: null;
        $places = $this->stock->consumptionByPlace($projectId);

        return Inertia::render('Stock/Consumption', [
            'places' => $places,
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'project_id' => $projectId,
            ],
            'overview' => [
                'places' => count($places),
                'lines' => collect($places)->sum(fn ($p) => count($p['lines'] ?? [])),
                'total_qty' => round((float) collect($places)->sum('total_qty'), 3),
                'total_cost_iqd' => round((float) collect($places)->sum('total_cost_iqd'), 2),
            ],
        ]);
    }

    public function createIn(): Response
    {
        $this->authorize('stockIn', StockMovement::class);

        $vault = app(\App\Services\SimpleVaultService::class);
        $projects = Project::query()->orderBy('name')->get(['id', 'name'])->map(function (Project $project) use ($vault) {
            return [
                'id' => $project->id,
                'name' => $project->name,
                'advance_usd' => $vault->projectAvailableCash($project->id, 'USD'),
                'advance_iqd' => $vault->projectAvailableCash($project->id, 'IQD'),
            ];
        });

        return Inertia::render('Stock/In/Create', [
            'items' => StockItem::query()->orderBy('name')->get([
                'id', 'name', 'sku', 'barcode', 'unit', 'quantity', 'currency',
                'purchase_price_usd', 'purchase_price_iqd', 'location',
            ])->map(function (StockItem $item) {
                $item->setAttribute('cost_currency', $item->costCurrency());
                $item->setAttribute('unit_cost', $item->unitCost());

                return $item;
            }),
            'projects' => $projects,
            'currencies' => DualCurrency::CURRENCIES,
            'paymentSources' => [
                StockMovement::PAYMENT_PROJECT_ADVANCE,
                StockMovement::PAYMENT_MAIN_VAULT,
            ],
            'defaults' => [
                'moved_on' => now()->toDateString(),
                'currency' => DualCurrency::IQD,
                'payment_source' => StockMovement::PAYMENT_PROJECT_ADVANCE,
            ],
        ]);
    }

    public function storeIn(StoreStockInRequest $request): RedirectResponse
    {
        $this->authorize('stockIn', StockMovement::class);

        try {
            $movement = $this->stock->stockIn($request->validated(), Auth::user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['payment_source' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('stock.dashboard')
            ->with('success', __('Stock in recorded.').' #'.$movement->id);
    }

    public function createOut(): Response
    {
        $this->authorize('stockOut', StockMovement::class);

        return Inertia::render('Stock/Out/Create', [
            'items' => StockItem::query()->orderBy('name')->get([
                'id', 'name', 'sku', 'barcode', 'unit', 'quantity', 'currency',
                'purchase_price_usd', 'purchase_price_iqd', 'min_quantity',
            ]),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'towers' => Tower::query()->orderBy('name')->get(['id', 'name', 'project_id']),
            'floors' => Floor::query()->orderBy('name')->get(['id', 'name', 'tower_id']),
            'staff' => Staff::query()->orderBy('name')->get(['id', 'name', 'kind', 'pay_model', 'role', 'trade']),
            'siteKinds' => StockMovement::SITE_KINDS,
            'placeSuggestions' => $this->stock->placeSuggestions(),
            'defaults' => [
                'moved_on' => now()->toDateString(),
                'issuer' => Auth::user()?->name,
            ],
        ]);
    }

    public function storeOut(StoreStockOutRequest $request): RedirectResponse
    {
        $this->authorize('stockOut', StockMovement::class);

        try {
            $movement = $this->stock->stockOut($request->validated(), Auth::user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['quantity' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('stock.dashboard')
            ->with('success', __('Stock out recorded.').' #'.$movement->id);
    }
}
