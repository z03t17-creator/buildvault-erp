<?php

namespace App\Http\Controllers;

use App\Http\Requests\Stock\StoreStockItemRequest;
use App\Http\Requests\Stock\UpdateStockItemRequest;
use App\Models\StockItem;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockItemController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', StockItem::class);

        $q = trim((string) $request->get('q', ''));
        $category = trim((string) $request->get('category', ''));

        $query = StockItem::query()
            ->with(['supplier:id,name'])
            ->orderBy('name');

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', '%'.$q.'%')
                    ->orWhere('sku', 'like', '%'.$q.'%')
                    ->orWhere('location', 'like', '%'.$q.'%');
            });
        }
        if ($category !== '') {
            $query->where('category', $category);
        }

        $items = $query->get()->map(function (StockItem $item) {
            $item->setAttribute('stock_value_iqd', $item->stockValueIqd());
            $item->setAttribute('is_low_stock', $item->isLowStock());
            $item->setAttribute('is_out_of_stock', $item->isOutOfStock());

            return $item;
        });

        $categories = StockItem::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return Inertia::render('Stock/Items/Index', [
            'items' => $items,
            'filters' => [
                'q' => $q,
                'category' => $category,
            ],
            'categories' => $categories,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', StockItem::class);

        return Inertia::render('Stock/Items/Create', [
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'defaults' => [
                'unit' => 'pcs',
                'quantity' => 0,
                'min_quantity' => 0,
                'purchase_price_iqd' => 0,
            ],
        ]);
    }

    public function store(StoreStockItemRequest $request): RedirectResponse
    {
        $this->authorize('create', StockItem::class);

        $data = $request->validated();
        $data['quantity'] = round((float) ($data['quantity'] ?? 0), 3);
        $data['min_quantity'] = round((float) ($data['min_quantity'] ?? 0), 3);
        $data['purchase_price_iqd'] = round((float) ($data['purchase_price_iqd'] ?? 0), 2);
        if (($data['sku'] ?? '') === '') {
            $data['sku'] = null;
        }

        $item = StockItem::query()->create($data);

        return redirect()
            ->route('stock.items.show', $item)
            ->with('success', __('Product saved.'));
    }

    public function show(StockItem $item): Response
    {
        $this->authorize('view', $item);

        $item->load(['supplier', 'movements' => function ($q) {
            $q->with(['user:id,name', 'project:id,name'])
                ->orderByDesc('moved_on')
                ->orderByDesc('id')
                ->limit(30);
        }]);

        $item->setAttribute('stock_value_iqd', $item->stockValueIqd());
        $item->setAttribute('is_low_stock', $item->isLowStock());
        $item->setAttribute('is_out_of_stock', $item->isOutOfStock());

        return Inertia::render('Stock/Items/Show', [
            'item' => $item,
        ]);
    }

    public function edit(StockItem $item): Response
    {
        $this->authorize('update', $item);

        return Inertia::render('Stock/Items/Edit', [
            'item' => $item->load('supplier:id,name'),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateStockItemRequest $request, StockItem $item): RedirectResponse
    {
        $this->authorize('update', $item);

        $data = $request->validated();
        $data['min_quantity'] = round((float) ($data['min_quantity'] ?? 0), 3);
        $data['purchase_price_iqd'] = round((float) ($data['purchase_price_iqd'] ?? 0), 2);
        if (($data['sku'] ?? '') === '') {
            $data['sku'] = null;
        }
        // Quantity is adjusted only via stock in/out movements.
        unset($data['quantity']);

        $item->update($data);

        return redirect()
            ->route('stock.items.show', $item)
            ->with('success', __('Product updated.'));
    }

    public function destroy(StockItem $item): RedirectResponse
    {
        $this->authorize('delete', $item);

        $item->delete();

        return redirect()
            ->route('stock.items.index')
            ->with('success', __('Product deleted.'));
    }
}
