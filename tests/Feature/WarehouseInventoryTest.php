<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Staff;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\StockService;
use App\Support\Roles;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WarehouseCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WarehouseInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(WarehouseCategorySeeder::class);
    }

    public function test_default_warehouse_categories_and_auto_sku_barcode(): void
    {
        foreach (['Doors', 'Electrical', 'Plumbing', 'Raw Materials'] as $name) {
            $this->assertTrue(
                StockCategory::query()->where('name', $name)->exists(),
                "Missing category {$name}"
            );
        }

        $stock = $this->userWithRole(Roles::STOCK_MANAGER);
        $doors = StockCategory::query()->where('name', 'Doors')->firstOrFail();

        $this->actingAs($stock)
            ->post(route('stock.items.store'), [
                'name' => 'MDF Door',
                'auto_sku' => true,
                'stock_category_id' => $doors->id,
                'unit' => 'Pcs',
                'quantity' => 0,
                'min_quantity' => 5,
                'currency' => 'IQD',
                'purchase_price' => 25000,
            ])
            ->assertRedirect();

        $item = StockItem::query()->where('name', 'MDF Door')->first();
        $this->assertNotNull($item);
        $this->assertNotNull($item->sku);
        $this->assertStringContainsString('BV-', $item->sku);
        $this->assertNotNull($item->barcode);
        $this->assertSame('Doors', $item->category);
        $this->assertSame('IQD', $item->currency);
        $this->assertSame(25000.0, (float) $item->purchase_price_iqd);
        $this->assertSame(0.0, (float) $item->purchase_price_usd);
    }

    public function test_stock_in_from_project_sulfa_posts_vault_expense(): void
    {
        $stock = $this->userWithRole(Roles::STOCK_MANAGER);
        $project = Project::query()->create(['name' => 'Sulfa Site', 'status' => 'active']);
        $vault = app(StockService::class); // ensure app boots
        unset($vault);

        $simple = app(\App\Services\SimpleVaultService::class);
        $simple->postAdvance([
            'occurred_on' => '2026-10-01',
            'amount' => 1000,
            'currency' => 'USD',
            'project_id' => $project->id,
            'note' => 'Client sulfa',
        ]);

        $item = StockItem::query()->create([
            'name' => 'Paint',
            'sku' => 'PNT-1',
            'unit' => 'Pcs',
            'quantity' => 0,
            'currency' => 'USD',
            'purchase_price_usd' => 50,
            'purchase_price_iqd' => 0,
        ]);

        $this->actingAs($stock)
            ->post(route('stock.in.store'), [
                'stock_item_id' => $item->id,
                'quantity' => 2,
                'moved_on' => '2026-10-10',
                'purchase_price' => 50,
                'currency' => 'USD',
                'payment_source' => 'project_advance',
                'project_id' => $project->id,
                'invoice_ref' => 'INV-S1',
            ])
            ->assertRedirect(route('stock.dashboard'));

        $item->refresh();
        $this->assertSame(2.0, (float) $item->quantity);

        $movement = StockMovement::query()->where('type', 'in')->latest('id')->first();
        $this->assertSame('project_advance', $movement->payment_source);
        $this->assertNotNull($movement->vault_line_id);

        $available = $simple->projectAvailableCash($project->id, 'USD');
        // 1000 advance − 100 hold = 900 available; purchase 100 → 800 left
        $this->assertSame(800.0, $available);
    }

    public function test_item_accepts_typed_category_and_custom_unit(): void
    {
        $stock = $this->userWithRole(Roles::STOCK_MANAGER);

        $this->actingAs($stock)
            ->post(route('stock.items.store'), [
                'name' => 'Custom Pipe',
                'auto_sku' => true,
                'category' => 'لوله',
                'unit' => 'تەن',
                'quantity' => 0,
                'currency' => 'IQD',
                'purchase_price' => 1000,
            ])
            ->assertRedirect();

        $item = StockItem::query()->where('name', 'Custom Pipe')->first();
        $this->assertNotNull($item);
        $this->assertSame('لوله', $item->category);
        $this->assertSame('تەن', $item->unit);
        $this->assertDatabaseHas('stock_categories', ['name' => 'لوله']);
        $this->assertSame(
            (int) StockCategory::query()->where('name', 'لوله')->value('id'),
            (int) $item->stock_category_id
        );

        // Reusing the same typed name (case-insensitive) must not create a duplicate.
        $this->actingAs($stock)
            ->post(route('stock.items.store'), [
                'name' => 'Custom Elbow',
                'auto_sku' => true,
                'category' => 'لوله',
                'unit' => 'دانە',
                'quantity' => 0,
                'currency' => 'IQD',
                'purchase_price' => 500,
            ])
            ->assertRedirect();

        $this->assertSame(1, StockCategory::query()->where('name', 'لوله')->count());
    }

    public function test_stock_item_can_be_priced_in_usd(): void
    {
        $stock = $this->userWithRole(Roles::STOCK_MANAGER);

        $this->actingAs($stock)
            ->post(route('stock.items.store'), [
                'name' => 'Imported Latch',
                'auto_sku' => true,
                'unit' => 'Pcs',
                'currency' => 'USD',
                'purchase_price' => 12.5,
            ])
            ->assertRedirect();

        $item = StockItem::query()->where('name', 'Imported Latch')->first();
        $this->assertNotNull($item);
        $this->assertSame('USD', $item->currency);
        $this->assertSame(12.5, (float) $item->purchase_price_usd);
        $this->assertSame(0.0, (float) $item->purchase_price_iqd);

        app(StockService::class)->stockIn([
            'stock_item_id' => $item->id,
            'quantity' => 4,
            'moved_on' => '2026-10-10',
            'purchase_price' => 10,
            'currency' => 'USD',
        ]);

        $item->refresh();
        $this->assertSame(4.0, (float) $item->quantity);
        $this->assertSame(10.0, (float) $item->purchase_price_usd);
        $this->assertSame(0.0, (float) $item->purchase_price_iqd);
    }

    public function test_receive_updates_weighted_average_and_shelf(): void
    {
        $service = app(StockService::class);
        $supplier = Supplier::query()->create(['name' => 'Mayorca']);
        $item = StockItem::query()->create([
            'name' => 'Cable',
            'sku' => 'CAB-1',
            'barcode' => 'CAB-1',
            'unit' => 'Meter',
            'quantity' => 10,
            'min_quantity' => 4,
            'purchase_price_iqd' => 1000,
        ]);

        $service->stockIn([
            'stock_item_id' => $item->id,
            'quantity' => 10,
            'moved_on' => '2026-10-09',
            'supplier_id' => $supplier->id,
            'purchase_price_iqd' => 2000,
            'invoice_ref' => 'INV-9',
            'shelf_zone' => 'A-12',
        ]);

        $item->refresh();
        $this->assertSame(20.0, (float) $item->quantity);
        $this->assertSame(1500.0, (float) $item->purchase_price_iqd);
        $this->assertSame('A-12', $item->location);

        $in = StockMovement::query()->where('type', StockMovement::TYPE_IN)->first();
        $this->assertSame('INV-9', $in->invoice_ref);
        $this->assertSame('A-12', $in->shelf_zone);
        $this->assertSame(20000.0, (float) $in->total_cost_iqd);
    }

    public function test_dispatch_to_building_place_with_staff_and_consumption_report(): void
    {
        $stock = $this->userWithRole(Roles::STOCK_MANAGER);
        $project = Project::query()->create(['name' => 'Mayorca', 'status' => 'active']);
        $staff = Staff::query()->create([
            'name' => 'Wasta Hunar',
            'pay_model' => Staff::PAY_UNIT,
            'kind' => Staff::KIND_UNIT,
            'role' => 'دەرگاچیی',
        ]);
        $item = StockItem::query()->create([
            'name' => 'Shaft door',
            'sku' => 'DOOR-SH',
            'barcode' => 'DOOR-SH',
            'unit' => 'Pcs',
            'quantity' => 12,
            'min_quantity' => 2,
            'purchase_price_iqd' => 8000,
            'stock_category_id' => StockCategory::query()->firstOrCreate(
                ['name' => 'Doors'],
                ['notes' => null]
            )->id,
            'category' => 'Doors',
        ]);

        $this->actingAs($stock)
            ->post(route('stock.out.store'), [
                'stock_item_id' => $item->id,
                'quantity' => 3,
                'moved_on' => '2026-10-09',
                'project_id' => $project->id,
                'site_kind' => StockMovement::SITE_BUILDING,
                'block' => 'A1',
                'zone' => 'Z2',
                'floor_label' => '3',
                'apartment_number' => '12',
                'staff_id' => $staff->id,
                'purpose' => 'Shaft install',
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertSame(9.0, (float) $item->quantity);

        $out = StockMovement::query()->where('type', StockMovement::TYPE_OUT)->first();
        $this->assertSame($staff->id, $out->staff_id);
        $this->assertSame('Wasta Hunar', $out->receiver);
        $this->assertSame('A1', $out->block);
        $this->assertSame('12', $out->apartment_number);
        $this->assertSame(24000.0, (float) $out->total_cost_iqd);

        $this->actingAs($stock)
            ->get(route('stock.consumption', ['project_id' => $project->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Stock/Consumption')
                ->has('places', 1)
                ->where('places.0.apartment_number', '12')
                ->where('places.0.lines.0.item_name', 'Shaft door')
                ->where('places.0.total_qty', 3)
            );

        $this->actingAs($stock)
            ->get(route('stock.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Stock/Dashboard')
                ->where('summary.low_stock', 0)
            );

        $source = file_get_contents(resource_path('js/Pages/Stock/Out/Create.jsx'));
        $this->assertStringContainsString('placeSuggestions', $source);
        $this->assertStringContainsString('warehouse_tab_dispatch', $source);
        $this->assertStringContainsString('SuggestionCombobox', $source);
        $this->assertStringContainsString('StockTabs', $source);

        $tabs = file_get_contents(resource_path('js/Components/StockTabs.jsx'));
        $this->assertStringContainsString('stock.dashboard', $tabs);
        $this->assertStringContainsString('stock.in.create', $tabs);
        $this->assertStringContainsString('stock.out.create', $tabs);
        $this->assertStringContainsString('warehouse_tab_balance', $tabs);
        $this->assertStringContainsString('warehouse_tab_receive', $tabs);
        $this->assertStringContainsString('warehouse_tab_dispatch', $tabs);

        $layout = file_get_contents(resource_path('js/Layouts/AuthenticatedLayout.jsx'));
        $this->assertStringNotContainsString("key: 'productions'", $layout);
    }

    public function test_dashboard_low_stock_badge_and_item_search_by_barcode(): void
    {
        $stock = $this->userWithRole(Roles::STOCK_MANAGER);
        StockItem::query()->create([
            'name' => 'Paint',
            'sku' => 'PNT-1',
            'barcode' => 'BC-PAINT-9',
            'unit' => 'Bag',
            'quantity' => 2,
            'min_quantity' => 5,
            'purchase_price_iqd' => 12000,
        ]);

        $this->actingAs($stock)
            ->get(route('stock.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.low_stock', 1)
                ->has('lowStockItems', 1)
                ->where('lowStockItems.0.name', 'Paint')
            );

        $this->actingAs($stock)
            ->get(route('stock.items.index', ['q' => 'BC-PAINT-9', 'status' => 'low']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Stock/Items/Index')
                ->has('items', 1)
                ->where('items.0.barcode', 'BC-PAINT-9')
                ->where('items.0.stock_status', 'low')
            );
    }
}
