<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\ProjectFinancialService;
use App\Services\StockService;
use App\Support\Permissions;
use App\Support\Roles;
use Database\Seeders\DemoStockSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StockPhase10Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_stock_manager_can_manage_stock_not_vault(): void
    {
        $stock = $this->userWithRole(Roles::STOCK_MANAGER);

        $this->actingAs($stock)->get(route('stock.dashboard'))->assertOk();
        $this->actingAs($stock)->get(route('stock.items.index'))->assertOk();
        $this->actingAs($stock)->get(route('stock.items.create'))->assertOk();
        $this->actingAs($stock)->get(route('stock.in.create'))->assertOk();
        $this->actingAs($stock)->get(route('stock.out.create'))->assertOk();
        $this->actingAs($stock)->get(route('stock.suppliers.index'))->assertOk();
        $this->actingAs($stock)->get(route('stock.movements.index'))->assertOk();

        $this->actingAs($stock)->get(route('dashboards.vault'))->assertForbidden();
        $this->actingAs($stock)->get(route('dashboards.payroll'))->assertForbidden();
        $this->actingAs($stock)->get(route('users.index'))->assertForbidden();
        $this->actingAs($stock)->get(route('payouts.index'))->assertForbidden();
    }

    public function test_boss_and_accountant_view_stock_only(): void
    {
        $boss = $this->userWithRole(Roles::BOSS_CONTRACTOR);
        $accountant = $this->userWithRole(Roles::ACCOUNTANT);

        foreach ([$boss, $accountant] as $user) {
            $this->actingAs($user)->get(route('stock.dashboard'))->assertOk();
            $this->actingAs($user)->get(route('stock.items.index'))->assertOk();
            $this->actingAs($user)->get(route('stock.movements.index'))->assertOk();
            $this->actingAs($user)->get(route('stock.items.create'))->assertForbidden();
            $this->actingAs($user)->get(route('stock.in.create'))->assertForbidden();
            $this->actingAs($user)->get(route('stock.out.create'))->assertForbidden();
            $this->actingAs($user)->get(route('stock.suppliers.create'))->assertForbidden();
        }
    }

    public function test_stock_in_and_out_http_math_and_negative_block(): void
    {
        $stock = $this->userWithRole(Roles::STOCK_MANAGER);
        $supplier = Supplier::query()->create(['name' => 'S1']);
        $item = StockItem::query()->create([
            'name' => 'Sand',
            'sku' => 'SAND-1',
            'unit' => 'm3',
            'quantity' => 0,
            'min_quantity' => 1,
            'purchase_price_iqd' => 20000,
            'supplier_id' => $supplier->id,
        ]);
        $project = Project::query()->create(['name' => 'Site A', 'status' => 'active']);

        $this->actingAs($stock)
            ->post(route('stock.in.store'), [
                'stock_item_id' => $item->id,
                'quantity' => 20,
                'moved_on' => '2026-09-28',
                'supplier_id' => $supplier->id,
                'purchase_price_iqd' => 21000,
                'project_id' => $project->id,
                'invoice_ref' => 'R-1',
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertSame('20.000', (string) $item->quantity);

        $this->actingAs($stock)
            ->post(route('stock.out.store'), [
                'stock_item_id' => $item->id,
                'quantity' => 5,
                'moved_on' => '2026-09-28',
                'project_id' => $project->id,
                'receiver' => 'Crew',
                'issuer' => 'Stock',
                'purpose' => 'Pour',
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertSame('15.000', (string) $item->quantity);

        $out = StockMovement::query()->where('type', 'out')->first();
        $this->assertNotNull($out);
        $this->assertSame('20.000', (string) $out->previous_qty);
        $this->assertSame('15.000', (string) $out->new_qty);

        $this->actingAs($stock)
            ->from(route('stock.out.create'))
            ->post(route('stock.out.store'), [
                'stock_item_id' => $item->id,
                'quantity' => 100,
                'moved_on' => '2026-09-28',
                'project_id' => $project->id,
            ])
            ->assertRedirect(route('stock.out.create'))
            ->assertSessionHasErrors('quantity');

        $this->assertSame('15.000', (string) $item->fresh()->quantity);
    }

    public function test_material_cost_hook_from_stock_out(): void
    {
        $service = app(StockService::class);
        $item = StockItem::query()->create([
            'name' => 'Rebar',
            'sku' => 'REB-X',
            'unit' => 'ton',
            'quantity' => 0,
            'min_quantity' => 0,
            'purchase_price_iqd' => 100000,
        ]);
        $project = Project::query()->create([
            'name' => 'Fin Project',
            'status' => 'active',
            'contract_value_iqd' => 1_000_000,
        ]);

        $service->stockIn([
            'stock_item_id' => $item->id,
            'quantity' => 10,
            'moved_on' => '2026-09-20',
            'purchase_price_iqd' => 100000,
        ]);
        $service->stockOut([
            'stock_item_id' => $item->id,
            'quantity' => 2,
            'moved_on' => '2026-09-21',
            'project_id' => $project->id,
        ]);

        $summary = app(ProjectFinancialService::class)->summary($project->fresh());
        $this->assertSame(200000.0, $summary['material_cost_iqd']);
        $this->assertArrayNotHasKey('material_cost', $summary['stubs']);
    }

    public function test_stock_manager_nav_includes_stock_and_attendance(): void
    {
        $stock = $this->userWithRole(Roles::STOCK_MANAGER);

        $this->actingAs($stock)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.nav', function ($nav) {
                    $keys = collect($nav)->values()->all();

                    return in_array('dashboard', $keys, true)
                        && in_array('stock', $keys, true)
                        && in_array('attendance', $keys, true)
                        && ! in_array('vault', $keys, true)
                        && ! in_array('payroll', $keys, true);
                })
                ->where('summary.total_items', 0)
                ->missing('summary.stock_module')
            );

        $role = Role::findByName(Roles::STOCK_MANAGER, 'web');
        $this->assertTrue($role->hasPermissionTo(Permissions::STOCK_VIEW_ANY));
        $this->assertTrue($role->hasPermissionTo(Permissions::STOCK_STOCK_OUT));
        $this->assertFalse($role->hasPermissionTo(Permissions::VAULT_VIEW));
    }

    public function test_attendance_routes_available_to_stock_manager(): void
    {
        $stock = $this->userWithRole(Roles::STOCK_MANAGER);

        $this->actingAs($stock)->get('/attendance')->assertOk();
        $this->actingAs($stock)->post('/attendance/check-in', [
            'date' => now()->toDateString(),
            'check_in' => '08:00',
            'worker_ids' => [],
        ])->assertSessionHasErrors(); // validation, not 404
    }

    public function test_demo_stock_seeder_is_idempotent(): void
    {
        $this->seed(DemoStockSeeder::class);
        $this->seed(DemoStockSeeder::class);

        $this->assertSame(1, Supplier::query()->where('name', DemoStockSeeder::SUPPLIER_NAME)->count());
        $this->assertGreaterThanOrEqual(3, StockItem::query()->count());
        $this->assertGreaterThan(0, StockMovement::query()->count());
    }
}
