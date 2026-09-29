<?php

namespace Tests\Feature;

use App\Models\EmployeeAdvance;
use App\Models\Expense;
use App\Models\Payout;
use App\Models\Project;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vault;
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

    public function test_boss_dashboard_props_are_financial_only(): void
    {
        $boss = User::query()->where('email', DemoUsersSeeder::BOSS_EMAIL)->firstOrFail();
        $vault = Vault::query()->where('name', VaultSeeder::NAME)->firstOrFail();
        $project = Project::query()->create([
            'name' => 'Profit Site',
            'contract_value_iqd' => 10_000_000,
        ]);

        Transaction::query()->create([
            'vault_id' => $vault->id,
            'project_id' => $project->id,
            'type' => Transaction::TYPE_MONEY_RECEIVED,
            'amount_iqd' => 2_500_000,
            'amount_usd' => 0,
            'exchange_rate' => 1310,
            'occurred_on' => now()->toDateString(),
            'description' => 'Client receipt',
            'created_by' => $boss->id,
        ]);

        $this->actingAs($boss)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('roleHome', Roles::BOSS_CONTRACTOR)
                ->has('summary.money_received_iqd')
                ->has('summary.money_spent_iqd')
                ->has('summary.available_iqd')
                ->has('summary.reserved_insurance_iqd')
                ->has('summary.payroll_totals_iqd')
                ->has('summary.advances_iqd')
                ->has('summary.stock_material_spend_iqd')
                ->has('summary.project_cards')
                ->where('summary.project_cards.0.name', 'Profit Site')
                ->missing('summary.users_active')
                ->missing('summary.last_logins')
                ->missing('summary.by_category')
            );
    }

    public function test_accountant_dashboard_props_cover_daily_ops(): void
    {
        $accountant = User::query()->where('email', DemoUsersSeeder::ACCOUNTANT_EMAIL)->firstOrFail();
        $vault = Vault::query()->where('name', VaultSeeder::NAME)->firstOrFail();
        $project = Project::query()->create(['name' => 'Ops Site']);
        $worker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Ops Worker',
        ]);

        Payout::query()->create([
            'vault_id' => $vault->id,
            'project_id' => $project->id,
            'worker_id' => $worker->id,
            'category' => Payout::CATEGORY_PAYROLL,
            'status' => Payout::STATUS_PENDING,
            'amount_usd' => 100,
            'amount_iqd' => 131_000,
            'created_by' => $accountant->id,
        ]);

        Expense::query()->create([
            'project_id' => $project->id,
            'category' => Expense::CATEGORY_FUEL,
            'amount_iqd' => 50_000,
            'expense_date' => now()->toDateString(),
            'approval_status' => Expense::STATUS_PENDING,
            'created_by' => $accountant->id,
        ]);

        EmployeeAdvance::query()->create([
            'worker_id' => $worker->id,
            'project_id' => $project->id,
            'amount_iqd' => 25_000,
            'remaining_iqd' => 25_000,
            'status' => EmployeeAdvance::STATUS_OPEN,
            'repayment_method' => EmployeeAdvance::REPAY_PAYROLL,
            'advanced_on' => now()->toDateString(),
            'reason' => 'Tools',
            'entered_by' => $accountant->id,
        ]);

        $this->actingAs($accountant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('roleHome', Roles::ACCOUNTANT)
                ->has('summary.payroll_due_count')
                ->has('summary.payroll_due_iqd')
                ->has('summary.pending_calculations')
                ->has('summary.advances_open_iqd')
                ->has('summary.penalties_pending_iqd')
                ->has('summary.insurance_held_iqd')
                ->has('summary.money_received_iqd')
                ->has('summary.available_payment_iqd')
                ->has('summary.recent_transactions')
                ->where('summary.payroll_due_count', 1)
                ->missing('summary.users_active')
                ->missing('summary.by_category')
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
                ->where('summary.total_items', 1)
                ->where('summary.by_category.0.category', 'Materials')
                ->missing('summary.money_received_iqd')
                ->missing('summary.users_active')
                ->missing('summary.available_payment_iqd')
                ->missing('summary.last_logins')
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
                'present' => ['project_cards', 'money_received_iqd'],
                'absent' => ['users_active', 'by_category', 'recent_transactions'],
            ],
            DemoUsersSeeder::ACCOUNTANT_EMAIL => [
                'present' => ['available_payment_iqd', 'recent_transactions'],
                'absent' => ['users_active', 'by_category', 'project_cards'],
            ],
            DemoUsersSeeder::STOCK_EMAIL => [
                'present' => ['by_category', 'recent_movements'],
                'absent' => ['users_active', 'money_received_iqd', 'available_payment_iqd'],
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
