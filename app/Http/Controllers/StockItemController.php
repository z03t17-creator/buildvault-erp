<?php

namespace App\Http\Controllers;

use App\Http\Requests\Stock\StoreStockItemRequest;
use App\Http\Requests\Stock\UpdateStockItemRequest;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Support\DualCurrency;
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
        $categoryId = $request->integer('category_id') ?: null;
        $status = (string) $request->get('status', '');

        $query = StockItem::query()
            ->with(['supplier:id,name', 'stockCategory:id,name'])
            ->orderBy('name');

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', '%'.$q.'%')
                    ->orWhere('sku', 'like', '%'.$q.'%')
                    ->orWhere('barcode', 'like', '%'.$q.'%')
                    ->orWhere('location', 'like', '%'.$q.'%');
            });
        }
        if ($categoryId) {
            $query->where('stock_category_id', $categoryId);
        }

        $items = $query->get()->map(function (StockItem $item) {
            $item->setAttribute('stock_value_iqd', $item->stockValueIqd());
            $item->setAttribute('stock_value_usd', $item->stockValueUsd());
            $item->setAttribute('stock_value', $item->stockValue());
            $item->setAttribute('average_unit_cost', $item->averageUnitCost());
            $item->setAttribute('cost_currency', $item->costCurrency());
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

        $categories = StockCategory::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $overview = [
            'products' => $items->count(),
            'stock_value_iqd' => round((float) $items->sum(fn (StockItem $item) => (float) $item->stock_value_iqd), 2),
            'stock_value_usd' => round((float) $items->sum(fn (StockItem $item) => (float) $item->stock_value_usd), 2),
            'low_stock' => $items->where('is_low_stock', true)->count(),
            'out_of_stock' => $items->where('is_out_of_stock', true)->count(),
            'total_quantity' => round((float) $items->sum(fn (StockItem $item) => (float) $item->quantity), 3),
        ];

        return Inertia::render('Stock/Items/Index', [
            'items' => $items,
            'filters' => [
                'q' => $q,
                'category_id' => $categoryId,
                'status' => in_array($status, ['ok', 'low', 'out'], true) ? $status : '',
            ],
            'categories' => $categories,
            'overview' => $overview,
            'units' => StockItem::UNITS,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', StockItem::class);

        return Inertia::render('Stock/Items/Create', [
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'categories' => StockCategory::query()->orderBy('name')->get(['id', 'name']),
            'units' => StockItem::UNITS,
            'currencies' => DualCurrency::CURRENCIES,
            'defaults' => [
                'unit' => 'Pcs',
                'quantity' => 0,
                'min_quantity' => 0,
                'currency' => DualCurrency::IQD,
                'purchase_price' => 0,
                'auto_sku' => true,
            ],
        ]);
    }

    public function store(StoreStockItemRequest $request): RedirectResponse
    {
        $this->authorize('create', StockItem::class);

        $data = $this->normalizeItemPayload($request->validated());
        $item = StockItem::query()->create($data);

        return redirect()
            ->route('stock.items.show', $item)
            ->with('success', __('Product saved.'));
    }

    public function show(StockItem $item): Response
    {
        $this->authorize('view', $item);

        $item->load([
            'supplier',
            'stockCategory:id,name',
            'movements' => function ($q) {
                $q->with(['user:id,name', 'project:id,name', 'staff:id,name'])
                    ->orderByDesc('moved_on')
                    ->orderByDesc('id')
                    ->limit(30);
            },
        ]);

        $item->setAttribute('stock_value_iqd', $item->stockValueIqd());
        $item->setAttribute('stock_value_usd', $item->stockValueUsd());
        $item->setAttribute('stock_value', $item->stockValue());
        $item->setAttribute('average_unit_cost', $item->averageUnitCost());
        $item->setAttribute('cost_currency', $item->costCurrency());
        $item->setAttribute('is_low_stock', $item->isLowStock());
        $item->setAttribute('is_out_of_stock', $item->isOutOfStock());
        $item->setAttribute('stock_status', $item->stockStatus());
        $item->setAttribute(
            'category_label',
            $item->stockCategory?->name ?: (trim((string) ($item->category ?? '')) ?: null)
        );

        return Inertia::render('Stock/Items/Show', [
            'item' => $item,
        ]);
    }

    public function print(StockItem $item): Response
    {
        $this->authorize('view', $item);

        $item->load([
            'supplier',
            'stockCategory:id,name',
            'movements' => function ($q) {
                $q->with(['user:id,name', 'project:id,name', 'staff:id,name'])
                    ->orderByDesc('moved_on')
                    ->orderByDesc('id')
                    ->limit(100);
            },
        ]);

        $item->setAttribute('stock_value_iqd', $item->stockValueIqd());
        $item->setAttribute('stock_value_usd', $item->stockValueUsd());
        $item->setAttribute('stock_value', $item->stockValue());
        $item->setAttribute('average_unit_cost', $item->averageUnitCost());
        $item->setAttribute('cost_currency', $item->costCurrency());
        $item->setAttribute('is_low_stock', $item->isLowStock());
        $item->setAttribute('is_out_of_stock', $item->isOutOfStock());
        $item->setAttribute('stock_status', $item->stockStatus());
        $item->setAttribute(
            'category_label',
            $item->stockCategory?->name ?: (trim((string) ($item->category ?? '')) ?: null)
        );

        return Inertia::render('Stock/Items/Print', [
            'item' => $item,
            'printedAt' => now()->timezone(config('app.timezone'))->format('Y-m-d H:i'),
        ]);
    }

    public function edit(StockItem $item): Response
    {
        $this->authorize('update', $item);

        return Inertia::render('Stock/Items/Edit', [
            'item' => $item->load(['supplier:id,name', 'stockCategory:id,name']),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'categories' => StockCategory::query()->orderBy('name')->get(['id', 'name']),
            'units' => StockItem::UNITS,
            'currencies' => DualCurrency::CURRENCIES,
        ]);
    }

    public function update(UpdateStockItemRequest $request, StockItem $item): RedirectResponse
    {
        $this->authorize('update', $item);

        $data = $this->normalizeItemPayload($request->validated(), includeQuantity: false);
        unset($data['quantity'], $data['auto_sku']);

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
            ->route('stock.dashboard')
            ->with('success', __('Product deleted.'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeItemPayload(array $data, bool $includeQuantity = true): array
    {
        if ($includeQuantity) {
            $data['quantity'] = round((float) ($data['quantity'] ?? 0), 3);
        }
        $data['min_quantity'] = round((float) ($data['min_quantity'] ?? 0), 3);

        $currency = strtoupper((string) ($data['currency'] ?? DualCurrency::IQD));
        if (! in_array($currency, DualCurrency::CURRENCIES, true)) {
            $currency = DualCurrency::IQD;
        }
        $amount = array_key_exists('purchase_price', $data) && $data['purchase_price'] !== null && $data['purchase_price'] !== ''
            ? round((float) $data['purchase_price'], 2)
            : round((float) (
                $currency === DualCurrency::USD
                    ? ($data['purchase_price_usd'] ?? 0)
                    : ($data['purchase_price_iqd'] ?? 0)
            ), 2);
        if ($amount < 0) {
            $amount = 0.0;
        }
        $data['currency'] = $currency;
        $data['purchase_price_usd'] = $currency === DualCurrency::USD ? $amount : 0.0;
        $data['purchase_price_iqd'] = $currency === DualCurrency::IQD ? $amount : 0.0;
        unset($data['purchase_price']);

        $categoryId = $data['stock_category_id'] ?? null;
        $typedCategory = trim((string) ($data['category'] ?? ''));
        $categoryName = null;

        if ($typedCategory !== '') {
            $existing = StockCategory::query()
                ->whereRaw('LOWER(name) = LOWER(?)', [$typedCategory])
                ->first();
            $category = $existing ?: StockCategory::query()->create(['name' => $typedCategory]);
            $categoryName = $category->name;
            $data['stock_category_id'] = (int) $category->id;
            $data['category'] = $categoryName;
        } elseif ($categoryId) {
            $categoryName = StockCategory::query()->whereKey($categoryId)->value('name');
            $data['category'] = $categoryName;
            $data['stock_category_id'] = (int) $categoryId;
        } else {
            $data['stock_category_id'] = null;
            $data['category'] = null;
        }

        $data['unit'] = trim((string) ($data['unit'] ?? ''));
        if ($data['unit'] === '') {
            $data['unit'] = 'Pcs';
        }

        $autoSku = ! empty($data['auto_sku']);
        unset($data['auto_sku']);

        if (($data['sku'] ?? '') === '' && $autoSku) {
            $data['sku'] = StockItem::generateSku(is_string($categoryName) ? $categoryName : null);
        }
        if (($data['sku'] ?? '') === '') {
            $data['sku'] = null;
        }

        if (($data['barcode'] ?? '') === '') {
            $data['barcode'] = $data['sku'] ? StockItem::generateBarcode($data['sku']) : null;
        }

        return $data;
    }
}
