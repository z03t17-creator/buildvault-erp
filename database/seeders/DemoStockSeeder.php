<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockService;
use App\Support\Roles;
use Illuminate\Database\Seeder;

/**
 * Demo stock suppliers, products, and movements (idempotent markers).
 * Runs only under SEED_DEMO — keeps Render boot seed slim.
 */
class DemoStockSeeder extends Seeder
{
    public const SUPPLIER_NAME = 'Erbil Building Supply (Demo)';

    public const MARKERS = [
        'DEMO-STOCK-CEMENT',
        'DEMO-STOCK-REBAR',
        'DEMO-STOCK-PAINT',
    ];

    public function run(): void
    {
        $supplier = Supplier::query()->firstOrCreate(
            ['name' => self::SUPPLIER_NAME],
            [
                'contact_name' => 'Karwan Demo',
                'phone' => '+9647700009911',
                'email' => 'supply-demo@zhako.test',
                'notes' => 'DEMO-STOCK-SUPPLIER',
            ],
        );

        $actor = User::role(Roles::STOCK_MANAGER)->first()
            ?? User::role(Roles::SUPER_ADMIN)->first();

        $cement = StockItem::query()->firstOrCreate(
            ['sku' => 'DEMO-CEM-50'],
            [
                'name' => 'Portland Cement 50kg',
                'category' => 'cement',
                'unit' => 'bag',
                'quantity' => 0,
                'min_quantity' => 40,
                'purchase_price_iqd' => 12500,
                'supplier_id' => $supplier->id,
                'location' => 'Yard A',
                'notes' => self::MARKERS[0],
            ],
        );

        $rebar = StockItem::query()->firstOrCreate(
            ['sku' => 'DEMO-REB-12'],
            [
                'name' => 'Rebar 12mm',
                'category' => 'steel',
                'unit' => 'ton',
                'quantity' => 0,
                'min_quantity' => 2,
                'purchase_price_iqd' => 950000,
                'supplier_id' => $supplier->id,
                'location' => 'Yard B',
                'notes' => self::MARKERS[1],
            ],
        );

        $paint = StockItem::query()->firstOrCreate(
            ['sku' => 'DEMO-PNT-20'],
            [
                'name' => 'Exterior Paint 20L',
                'category' => 'finishing',
                'unit' => 'pail',
                'quantity' => 0,
                'min_quantity' => 10,
                'purchase_price_iqd' => 45000,
                'supplier_id' => $supplier->id,
                'location' => 'Store room',
                'notes' => self::MARKERS[2],
            ],
        );

        // Skip movements if already seeded (qty already moved).
        if ((float) $cement->quantity > 0 || (float) $rebar->quantity > 0) {
            return;
        }

        $service = app(StockService::class);
        $project = Project::query()->where('name', DemoHierarchySeeder::PROJECT_NAME)->first();

        // Stock OUT requires a project for material-cost attribution.
        if (! $project) {
            $service->stockIn([
                'stock_item_id' => $cement->id,
                'quantity' => 120,
                'moved_on' => now()->subDays(2)->toDateString(),
                'supplier_id' => $supplier->id,
                'purchase_price_iqd' => 12500,
                'invoice_ref' => 'INV-DEMO-CEM-1',
                'notes' => self::MARKERS[0].' IN',
            ], $actor);

            $service->stockIn([
                'stock_item_id' => $rebar->id,
                'quantity' => 8,
                'moved_on' => now()->subDays(2)->toDateString(),
                'supplier_id' => $supplier->id,
                'purchase_price_iqd' => 950000,
                'invoice_ref' => 'INV-DEMO-REB-1',
                'notes' => self::MARKERS[1].' IN',
            ], $actor);

            $service->stockIn([
                'stock_item_id' => $paint->id,
                'quantity' => 6,
                'moved_on' => now()->subDay()->toDateString(),
                'supplier_id' => $supplier->id,
                'purchase_price_iqd' => 45000,
                'invoice_ref' => 'INV-DEMO-PNT-1',
                'notes' => self::MARKERS[2].' IN (low stock demo)',
            ], $actor);

            return;
        }

        $service->stockIn([
            'stock_item_id' => $cement->id,
            'quantity' => 120,
            'moved_on' => now()->subDays(2)->toDateString(),
            'supplier_id' => $supplier->id,
            'purchase_price_iqd' => 12500,
            'invoice_ref' => 'INV-DEMO-CEM-1',
            'notes' => self::MARKERS[0].' IN',
        ], $actor);

        $service->stockIn([
            'stock_item_id' => $rebar->id,
            'quantity' => 8,
            'moved_on' => now()->subDays(2)->toDateString(),
            'supplier_id' => $supplier->id,
            'purchase_price_iqd' => 950000,
            'invoice_ref' => 'INV-DEMO-REB-1',
            'notes' => self::MARKERS[1].' IN',
        ], $actor);

        $service->stockIn([
            'stock_item_id' => $paint->id,
            'quantity' => 6,
            'moved_on' => now()->subDay()->toDateString(),
            'supplier_id' => $supplier->id,
            'purchase_price_iqd' => 45000,
            'invoice_ref' => 'INV-DEMO-PNT-1',
            'notes' => self::MARKERS[2].' IN (low stock demo)',
        ], $actor);

        $service->stockOut([
            'stock_item_id' => $cement->id,
            'quantity' => 30,
            'moved_on' => now()->toDateString(),
            'project_id' => $project->id,
            'receiver' => 'Site foreman',
            'issuer' => $actor?->name ?? 'Stock Manager',
            'purpose' => 'Foundation pour',
            'reference' => 'OUT-DEMO-CEM-1',
            'notes' => self::MARKERS[0].' OUT',
        ], $actor);

        $service->stockOut([
            'stock_item_id' => $rebar->id,
            'quantity' => 1.5,
            'moved_on' => now()->toDateString(),
            'project_id' => $project->id,
            'receiver' => 'Steel crew',
            'issuer' => $actor?->name ?? 'Stock Manager',
            'purpose' => 'Column cages',
            'reference' => 'OUT-DEMO-REB-1',
            'notes' => self::MARKERS[1].' OUT',
        ], $actor);
    }
}
