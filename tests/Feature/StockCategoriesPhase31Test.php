<?php

namespace Tests\Feature;

use App\Models\StockCategory;
use App\Models\StockItem;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StockCategoriesPhase31Test extends TestCase
{
    use RefreshDatabase;

    public function test_stock_manager_can_crud_categories_and_assign_to_products(): void
    {
        $this->actingAsRole(Roles::STOCK_MANAGER);

        $this->get(route('stock.categories.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Stock/Categories/Index')
                ->where('overview.categories', 0)
            );

        $this->post(route('stock.categories.store'), [
            'name' => 'Cement',
            'notes' => 'Bags and bulk',
        ])->assertRedirect(route('stock.categories.index'));

        $category = StockCategory::query()->where('name', 'Cement')->first();
        $this->assertNotNull($category);

        $this->post(route('stock.items.store'), [
            'name' => 'Cement Bag 50kg',
            'sku' => 'CEM-50',
            'stock_category_id' => $category->id,
            'unit' => 'bag',
            'quantity' => 10,
            'min_quantity' => 5,
            'purchase_price_iqd' => 12000,
        ])->assertRedirect();

        $item = StockItem::query()->where('sku', 'CEM-50')->first();
        $this->assertNotNull($item);
        $this->assertSame($category->id, (int) $item->stock_category_id);
        $this->assertSame('Cement', $item->category);

        $this->get(route('stock.items.index', ['category_id' => $category->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Stock/Items/Index')
                ->has('items', 1)
                ->where('filters.category_id', $category->id)
                ->has('categories', 1)
            );

        $this->put(route('stock.categories.update', $category), [
            'name' => 'Cement & Binder',
            'notes' => 'Updated',
        ])->assertRedirect(route('stock.categories.index'));

        $item->refresh();
        $this->assertSame('Cement & Binder', $item->category);

        $this->delete(route('stock.categories.destroy', $category))
            ->assertRedirect(route('stock.categories.index'));

        $item->refresh();
        $this->assertNull($item->stock_category_id);
        $this->assertNull($item->category);
        $this->assertDatabaseMissing('stock_categories', ['id' => $category->id]);
    }

    public function test_boss_can_view_categories_but_not_create(): void
    {
        StockCategory::query()->create(['name' => 'Steel']);

        $this->actingAsRole(Roles::BOSS_CONTRACTOR);

        $this->get(route('stock.categories.index'))->assertOk();

        $this->post(route('stock.categories.store'), [
            'name' => 'Blocked',
        ])->assertForbidden();
    }
}
