<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Project;
use App\Models\StockItem;
use App\Models\Transaction;
use App\Services\ProjectFinancialService;
use App\Services\StockService;
use App\Support\Roles;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Phase 11 — stock OUT → project material cost rollup (no expense double-count).
 */
class StockProjectPhase11Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_stock_out_requires_project(): void
    {
        $stock = $this->userWithRole(Roles::STOCK_MANAGER);
        $item = StockItem::query()->create([
            'name' => 'Cement',
            'sku' => 'CEM-P11',
            'unit' => 'bag',
            'quantity' => 50,
            'min_quantity' => 5,
            'purchase_price_iqd' => 12000,
        ]);

        $this->actingAs($stock)
            ->from(route('stock.out.create'))
            ->post(route('stock.out.store'), [
                'stock_item_id' => $item->id,
                'quantity' => 5,
                'moved_on' => '2026-09-28',
            ])
            ->assertRedirect(route('stock.out.create'))
            ->assertSessionHasErrors('project_id');

        $this->assertSame('50.000', (string) $item->fresh()->quantity);
    }

    public function test_material_cost_rollup_after_stock_out(): void
    {
        $service = app(StockService::class);
        $item = StockItem::query()->create([
            'name' => 'Rebar',
            'sku' => 'REB-P11',
            'unit' => 'ton',
            'quantity' => 0,
            'min_quantity' => 0,
            'purchase_price_iqd' => 100000,
        ]);
        $project = Project::query()->create([
            'name' => 'Phase 11 Tower',
            'status' => 'active',
            'contract_value_iqd' => 5_000_000,
        ]);

        $service->stockIn([
            'stock_item_id' => $item->id,
            'quantity' => 10,
            'moved_on' => '2026-09-20',
            'purchase_price_iqd' => 100000,
        ]);

        // Stock-in must not inflate material cost or create expenses/vault rows.
        $this->assertSame(0.0, app(ProjectFinancialService::class)->materialCostIqd($project->fresh()));
        $this->assertSame(0, Expense::query()->count());
        $this->assertSame(0, Transaction::query()->count());

        $service->stockOut([
            'stock_item_id' => $item->id,
            'quantity' => 2,
            'moved_on' => '2026-09-21',
            'project_id' => $project->id,
            'purpose' => 'Columns',
        ]);
        $service->stockOut([
            'stock_item_id' => $item->id,
            'quantity' => 1.5,
            'moved_on' => '2026-09-22',
            'project_id' => $project->id,
        ]);

        $financials = app(ProjectFinancialService::class);
        $summary = $financials->summary($project->fresh());

        // 2×100000 + 1.5×100000 = 350000
        $this->assertSame(350000.0, $summary['material_cost_iqd']);
        $this->assertSame(350000.0, $financials->materialCostIqd($project->fresh()));
        $this->assertArrayNotHasKey('material_cost', $summary['stubs']);

        // Net position subtracts material cost.
        $this->assertSame(-350000.0, $summary['net_position_iqd']);

        $recent = $financials->recentMaterialsUsed($project->fresh());
        $this->assertCount(2, $recent);
        $this->assertSame('Rebar', $recent[0]['item_name']);
        $this->assertSame(1.5, $recent[0]['quantity']);
        $this->assertSame(150000.0, $recent[0]['line_value_iqd']);
        $this->assertSame(8.0, $recent[0]['previous_qty']);
        $this->assertSame(6.5, $recent[0]['new_qty']);
    }

    public function test_boss_sees_material_cost_and_recent_materials_on_project_show(): void
    {
        $stock = app(StockService::class);
        $boss = $this->userWithRole(Roles::BOSS_CONTRACTOR);
        $item = StockItem::query()->create([
            'name' => 'Paint',
            'sku' => 'PNT-P11',
            'unit' => 'pail',
            'quantity' => 0,
            'min_quantity' => 0,
            'purchase_price_iqd' => 45000,
        ]);
        $project = Project::query()->create([
            'name' => 'Show Materials',
            'status' => 'active',
            'contract_value_iqd' => 1_000_000,
        ]);

        $stock->stockIn([
            'stock_item_id' => $item->id,
            'quantity' => 4,
            'moved_on' => '2026-09-20',
            'purchase_price_iqd' => 45000,
        ]);
        $stock->stockOut([
            'stock_item_id' => $item->id,
            'quantity' => 2,
            'moved_on' => '2026-09-21',
            'project_id' => $project->id,
            'purpose' => 'Facade',
        ]);

        $this->actingAs($boss)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Show')
                ->where('canViewFinancials', true)
                ->where('financialSummary.material_cost_iqd', 90000)
                ->has('recentMaterials', 1)
                ->where('recentMaterials.0.item_name', 'Paint')
                ->where('recentMaterials.0.line_value_iqd', 90000)
                ->where('recentMaterials.0.previous_qty', 4)
                ->where('recentMaterials.0.new_qty', 2)
            );
    }

    public function test_stock_manager_cannot_view_projects(): void
    {
        $stockUser = $this->userWithRole(Roles::STOCK_MANAGER);
        $project = Project::query()->create(['name' => 'Hidden Fin', 'status' => 'active']);

        // RBAC unchanged: Stock Manager operates stock only — no project financials.
        $this->actingAs($stockUser)
            ->get(route('projects.show', $project))
            ->assertForbidden();
    }

    public function test_http_stock_out_updates_project_material_cost(): void
    {
        $stockUser = $this->userWithRole(Roles::STOCK_MANAGER);
        $item = StockItem::query()->create([
            'name' => 'Sand',
            'sku' => 'SND-P11',
            'unit' => 'm3',
            'quantity' => 20,
            'min_quantity' => 1,
            'purchase_price_iqd' => 25000,
        ]);
        $project = Project::query()->create([
            'name' => 'HTTP Cost',
            'status' => 'active',
            'contract_value_iqd' => 500_000,
        ]);

        $this->actingAs($stockUser)
            ->post(route('stock.out.store'), [
                'stock_item_id' => $item->id,
                'quantity' => 3,
                'moved_on' => '2026-09-28',
                'project_id' => $project->id,
                'receiver' => 'Crew',
            ])
            ->assertRedirect();

        $this->assertSame(75000.0, app(ProjectFinancialService::class)->materialCostIqd($project->fresh()));
        $this->assertSame(0, Expense::query()->count());
    }
}
