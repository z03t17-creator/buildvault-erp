<?php

namespace App\Http\Controllers;

use App\Http\Requests\Stock\StoreStockCategoryRequest;
use App\Http\Requests\Stock\UpdateStockCategoryRequest;
use App\Models\StockCategory;
use App\Models\StockItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', StockCategory::class);

        $q = trim((string) $request->get('q', ''));

        $query = StockCategory::query()
            ->withCount('stockItems')
            ->orderBy('name');

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', '%'.$q.'%')
                    ->orWhere('notes', 'like', '%'.$q.'%');
            });
        }

        $categories = $query->get();

        $overview = [
            'categories' => $categories->count(),
            'products_linked' => (int) $categories->sum('stock_items_count'),
            'empty_categories' => $categories->where('stock_items_count', 0)->count(),
        ];

        return Inertia::render('Stock/Categories/Index', [
            'categories' => $categories,
            'filters' => ['q' => $q],
            'overview' => $overview,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', StockCategory::class);

        return Inertia::render('Stock/Categories/Create');
    }

    public function store(StoreStockCategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', StockCategory::class);

        $category = StockCategory::query()->create($request->validated());

        return redirect()
            ->route('stock.categories.index')
            ->with('success', __('stock_categories_saved'));
    }

    public function edit(StockCategory $stockCategory): Response
    {
        $this->authorize('update', $stockCategory);

        $stockCategory->loadCount('stockItems');

        return Inertia::render('Stock/Categories/Edit', [
            'category' => $stockCategory,
        ]);
    }

    public function update(UpdateStockCategoryRequest $request, StockCategory $stockCategory): RedirectResponse
    {
        $this->authorize('update', $stockCategory);

        $data = $request->validated();
        $stockCategory->update($data);

        // Keep denormalized product category labels in sync.
        StockItem::query()
            ->where('stock_category_id', $stockCategory->id)
            ->update(['category' => $stockCategory->name]);

        return redirect()
            ->route('stock.categories.index')
            ->with('success', __('stock_categories_updated'));
    }

    public function destroy(StockCategory $stockCategory): RedirectResponse
    {
        $this->authorize('delete', $stockCategory);

        StockItem::query()
            ->where('stock_category_id', $stockCategory->id)
            ->update([
                'stock_category_id' => null,
                'category' => null,
            ]);

        $stockCategory->delete();

        return redirect()
            ->route('stock.categories.index')
            ->with('success', __('stock_categories_deleted'));
    }
}
