<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $service;

    private StockItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(StockService::class);

        $supplier = Supplier::query()->create(['name' => 'Test Supplier']);
        $this->item = StockItem::query()->create([
            'name' => 'Cement',
            'sku' => 'CEM-1',
            'category' => 'cement',
            'unit' => 'bag',
            'quantity' => 0,
            'min_quantity' => 10,
            'purchase_price_iqd' => 10000,
            'supplier_id' => $supplier->id,
        ]);
    }

    public function test_stock_in_increases_quantity_and_records_ledger(): void
    {
        $movement = $this->service->stockIn([
            'stock_item_id' => $this->item->id,
            'quantity' => 50,
            'moved_on' => '2026-09-28',
            'purchase_price_iqd' => 11000,
            'invoice_ref' => 'INV-1',
        ]);

        $this->item->refresh();
        $this->assertSame('50.000', (string) $this->item->quantity);
        $this->assertSame('11000.00', (string) $this->item->purchase_price_iqd);
        $this->assertSame('in', $movement->type);
        $this->assertSame('0.000', (string) $movement->previous_qty);
        $this->assertSame('50.000', (string) $movement->new_qty);
        $this->assertSame('11000.00', (string) $movement->purchase_price_iqd);
    }

    public function test_stock_out_decreases_quantity_with_previous_and_new(): void
    {
        $project = Project::query()->create(['name' => 'Unit Site', 'status' => 'active']);

        $this->service->stockIn([
            'stock_item_id' => $this->item->id,
            'quantity' => 40,
            'moved_on' => '2026-09-27',
        ]);

        $movement = $this->service->stockOut([
            'stock_item_id' => $this->item->id,
            'quantity' => 15,
            'moved_on' => '2026-09-28',
            'project_id' => $project->id,
            'purpose' => 'Site use',
        ]);

        $this->item->refresh();
        $this->assertSame('25.000', (string) $this->item->quantity);
        $this->assertSame('out', $movement->type);
        $this->assertSame('40.000', (string) $movement->previous_qty);
        $this->assertSame('25.000', (string) $movement->new_qty);
        $this->assertSame('10000.00', (string) $movement->purchase_price_iqd);
        $this->assertSame($project->id, $movement->project_id);
        $this->assertSame(150000.0, $this->service->materialCostForProject($project->id));
    }

    public function test_stock_out_requires_project(): void
    {
        $this->service->stockIn([
            'stock_item_id' => $this->item->id,
            'quantity' => 10,
            'moved_on' => '2026-09-28',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stock-out requires a project');

        $this->service->stockOut([
            'stock_item_id' => $this->item->id,
            'quantity' => 5,
            'moved_on' => '2026-09-28',
        ]);
    }

    public function test_stock_out_blocks_negative_quantity(): void
    {
        $project = Project::query()->create(['name' => 'Neg Block', 'status' => 'active']);

        $this->service->stockIn([
            'stock_item_id' => $this->item->id,
            'quantity' => 10,
            'moved_on' => '2026-09-28',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->service->stockOut([
            'stock_item_id' => $this->item->id,
            'quantity' => 11,
            'moved_on' => '2026-09-28',
            'project_id' => $project->id,
        ]);
    }

    public function test_dashboard_summary_counts_value_and_alerts(): void
    {
        $this->service->stockIn([
            'stock_item_id' => $this->item->id,
            'quantity' => 5,
            'moved_on' => now()->toDateString(),
            'purchase_price_iqd' => 10000,
        ]);

        StockItem::query()->create([
            'name' => 'Empty',
            'sku' => 'EMPTY-1',
            'unit' => 'pcs',
            'quantity' => 0,
            'min_quantity' => 1,
            'purchase_price_iqd' => 100,
        ]);

        $summary = $this->service->dashboardSummary();

        $this->assertSame(2, $summary['total_items']);
        $this->assertSame(50000.0, $summary['stock_value_iqd']);
        $this->assertSame(1, $summary['low_stock']);
        $this->assertSame(1, $summary['out_of_stock']);
        $this->assertSame(5.0, $summary['today_in_qty']);
    }
}
