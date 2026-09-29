<?php

namespace Tests\Feature;

use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Worker;
use App\Support\Roles;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoleDashboardPhase14Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(VaultSeeder::class);
        $this->seed(UserSeeder::class);
        $this->seed(DemoUsersSeeder::class);
    }

    public function test_super_admin_dashboard_props_include_available_cash_charts_and_modules(): void
    {
        $admin = User::query()->where('email', UserSeeder::ADMIN_EMAIL)->firstOrFail();
        $admin->forceFill([
            'status' => User::STATUS_ACTIVE,
            'last_login_at' => now()->subHour(),
        ])->save();

        Worker::query()->create([
            'name' => 'Unclassified Person',
            'labor_kind' => Worker::LABOR_KIND_UNCLASSIFIED,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('roleHome', Roles::SUPER_ADMIN)
                ->has('summary.available_iqd')
                ->has('summary.available_usd')
                ->has('summary.charts.available')
                ->has('summary.charts.spend_usd')
                ->has('summary.charts.spend_iqd')
                ->has('summary.charts.locked_free.usd')
                ->has('summary.charts.locked_free.iqd')
                ->has('summary.unclassified_people')
                ->where('summary.unclassified_people', 1)
                ->missing('summary.last_logins')
                ->missing('summary.health')
                ->missing('summary.vault_balance_iqd')
                ->missing('summary.by_category')
            );
    }

    public function test_boss_dashboard_props_include_available_cash_charts_and_modules(): void
    {
        $boss = User::query()->where('email', DemoUsersSeeder::BOSS_EMAIL)->firstOrFail();

        Worker::query()->create([
            'name' => 'Boss Unclassified Person',
            'labor_kind' => Worker::LABOR_KIND_UNCLASSIFIED,
        ]);

        $this->actingAs($boss)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('roleHome', Roles::BOSS_CONTRACTOR)
                ->has('summary.available_iqd')
                ->has('summary.available_usd')
                ->has('summary.charts.available')
                ->has('summary.charts.spend_usd')
                ->has('summary.charts.spend_iqd')
                ->has('summary.charts.locked_free.usd')
                ->has('summary.charts.locked_free.iqd')
                ->has('summary.unclassified_people')
                ->where('summary.unclassified_people', 1)
                ->missing('summary.project_cards')
                ->missing('summary.money_received_iqd')
                ->missing('summary.users_active')
                ->missing('summary.last_logins')
                ->missing('summary.health')
                ->missing('summary.workbook_bundled')
                ->missing('summary.by_category')
            );
    }

    public function test_accountant_dashboard_props_include_available_cash_charts_and_modules(): void
    {
        $accountant = User::query()->where('email', DemoUsersSeeder::ACCOUNTANT_EMAIL)->firstOrFail();

        Worker::query()->create([
            'name' => 'Accountant Unclassified Person',
            'labor_kind' => Worker::LABOR_KIND_UNCLASSIFIED,
        ]);

        $this->actingAs($accountant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('roleHome', Roles::ACCOUNTANT)
                ->has('summary.available_iqd')
                ->has('summary.available_usd')
                ->has('summary.charts.available')
                ->has('summary.charts.spend_usd')
                ->has('summary.charts.spend_iqd')
                ->has('summary.charts.locked_free.usd')
                ->has('summary.charts.locked_free.iqd')
                ->has('summary.unclassified_people')
                ->where('summary.unclassified_people', 1)
                ->missing('summary.payroll_due_count')
                ->missing('summary.available_payment_iqd')
                ->missing('summary.recent_transactions')
                ->missing('summary.money_received_iqd')
                ->missing('summary.users_active')
                ->missing('summary.workbook_bundled')
                ->missing('summary.by_category')
                ->missing('summary.last_logins')
                ->missing('summary.health')
            );
    }

    public function test_stock_manager_dashboard_is_stock_only_with_categories(): void
    {
        $stock = User::query()->where('email', DemoUsersSeeder::STOCK_EMAIL)->firstOrFail();

        $item = StockItem::query()->create([
            'name' => 'Cement',
            'sku' => 'CEM-1',
            'category' => 'Materials',
            'unit' => 'bag',
            'quantity' => 10,
            'min_quantity' => 5,
            'purchase_price_iqd' => 12_000,
        ]);

        StockMovement::query()->create([
            'stock_item_id' => $item->id,
            'type' => StockMovement::TYPE_IN,
            'quantity' => 10,
            'previous_qty' => 0,
            'new_qty' => 10,
            'purchase_price_iqd' => 12_000,
            'moved_on' => now()->toDateString(),
            'user_id' => $stock->id,
        ]);

        $this->actingAs($stock)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('roleHome', Roles::STOCK_MANAGER)
                ->has('summary.total_items')
                ->has('summary.stock_value_iqd')
                ->has('summary.low_stock')
                ->has('summary.out_of_stock')
                ->has('summary.by_category')
                ->has('summary.recent_movements')
                ->has('summary.charts')
                ->has('summary.charts.today_flow')
                ->has('summary.charts.health')
                ->has('summary.charts.value_by_category')
                ->where('summary.total_items', 1)
                ->where('summary.by_category.0.category', 'Materials')
                ->missing('summary.money_received_iqd')
                ->missing('summary.users_active')
                ->missing('summary.available_payment_iqd')
                ->missing('summary.last_logins')
                ->missing('summary.available_usd')
            );

        $this->actingAs($stock)->get(route('stock.dashboard'))->assertOk();
    }

    public function test_roles_cannot_receive_another_roles_dashboard_keys(): void
    {
        $cases = [
            UserSeeder::ADMIN_EMAIL => [
                'present' => ['available_usd', 'available_iqd', 'charts', 'unclassified_people'],
                'absent' => ['by_category', 'available_payment_iqd', 'users_active', 'health', 'last_logins'],
            ],
            DemoUsersSeeder::BOSS_EMAIL => [
                'present' => ['available_usd', 'available_iqd', 'charts', 'unclassified_people'],
                'absent' => ['users_active', 'by_category', 'recent_transactions', 'project_cards', 'workbook_bundled', 'health', 'last_logins'],
            ],
            DemoUsersSeeder::ACCOUNTANT_EMAIL => [
                'present' => ['available_usd', 'available_iqd', 'charts', 'unclassified_people'],
                'absent' => [
                    'users_active',
                    'by_category',
                    'project_cards',
                    'workbook_bundled',
                    'available_payment_iqd',
                    'recent_transactions',
                    'payroll_due_count',
                    'health',
                    'last_logins',
                ],
            ],
            DemoUsersSeeder::STOCK_EMAIL => [
                'present' => ['by_category', 'recent_movements', 'charts', 'total_items', 'stock_value_iqd'],
                'absent' => ['users_active', 'money_received_iqd', 'available_payment_iqd', 'available_usd', 'workbook_bundled'],
            ],
        ];

        foreach ($cases as $email => $expect) {
            $user = User::query()->where('email', $email)->firstOrFail();
            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertInertia(function (Assert $page) use ($expect) {
                    foreach ($expect['present'] as $key) {
                        $page->has('summary.'.$key);
                    }
                    foreach ($expect['absent'] as $key) {
                        $page->missing('summary.'.$key);
                    }

                    return $page;
                });
        }
    }
}
