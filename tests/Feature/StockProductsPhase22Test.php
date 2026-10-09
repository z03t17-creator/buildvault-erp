<?php

namespace Tests\Feature;

use App\Models\StockCategory;
use App\Models\StockItem;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StockProductsPhase22Test extends TestCase
{
    use RefreshDatabase;

    public function test_stock_manager_sees_qty_price_overview_and_filters(): void
    {
        $cement = StockCategory::query()->create(['name' => 'cement']);
        $finishing = StockCategory::query()->create(['name' => 'finishing']);
        $hardware = StockCategory::query()->create(['name' => 'hardware']);

        StockItem::query()->create([
            'name' => 'Cement Bag',
            'sku' => 'CEM-50',
            'category' => 'cement',
            'stock_category_id' => $cement->id,
            'unit' => 'bag',
            'quantity' => 90,
            'min_quantity' => 40,
            'purchase_price_iqd' => 12500,
            'location' => 'Yard A',
        ]);

        StockItem::query()->create([
            'name' => 'Paint Can',
            'sku' => 'PNT-20',
            'category' => 'finishing',
            'stock_category_id' => $finishing->id,
            'unit' => 'can',
            'quantity' => 5,
            'min_quantity' => 10,
            'purchase_price_iqd' => 45000,
            'location' => 'Store',
        ]);

        StockItem::query()->create([
            'name' => 'Empty Fastener',
            'sku' => 'FST-0',
            'category' => 'hardware',
            'stock_category_id' => $hardware->id,
            'unit' => 'box',
            'quantity' => 0,
            'min_quantity' => 2,
            'purchase_price_iqd' => 8000,
        ]);

        $this->actingAsRole(Roles::STOCK_MANAGER);

        $this->get(route('stock.items.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Stock/Items/Index')
                ->has('items', 3)
                ->where('overview.products', 3)
                ->where('overview.low_stock', 1)
                ->where('overview.out_of_stock', 1)
                ->where('overview.stock_value_iqd', 1350000)
                ->has('categories', 3)
                ->has('filters')
            );

        $this->get(route('stock.items.index', ['category_id' => $cement->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Stock/Items/Index')
                ->has('items', 1)
                ->where('filters.category_id', $cement->id)
                ->where('overview.products', 1)
                ->where('overview.stock_value_iqd', 1_125_000)
            );

        $this->get(route('stock.items.index', ['q' => 'Paint']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('items', 1)
                ->where('items.0.name', 'Paint Can')
                ->where('overview.low_stock', 1)
            );
    }

    public function test_create_form_exposes_shared_categories(): void
    {
        StockCategory::query()->create(['name' => 'Steel']);

        $this->actingAsRole(Roles::STOCK_MANAGER);

        $this->get(route('stock.items.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Stock/Items/Create')
                ->has('categories', 1)
                ->has('suppliers')
            );
    }
}
