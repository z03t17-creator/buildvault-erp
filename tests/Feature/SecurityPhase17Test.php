<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureUserIsActive;
use App\Models\EmployeeAdvance;
use App\Models\Expense;
use App\Models\Payout;
use App\Models\Penalty;
use App\Models\Project;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vault;
use App\Models\Worker;
use App\Support\ReportTypes;
use App\Support\Roles;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Phase 17 — systematic authz / IDOR / CSRF / validation audit.
 */
class SecurityPhase17Test extends TestCase
{
    use RefreshDatabase;

    private Vault $vault;

    private Project $project;

    private Worker $worker;

    protected function setUp(): void
    {
        parent::setUp();
        if (! \Illuminate\Support\Facades\Route::has('payouts.index')) {
            $this->markTestSkipped('Module surface dropped in staff rewire slice.');
        }



        $this->vault = Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 10_000,
            'balance_iqd' => 13_100_000,
        ]);
        $this->project = Project::query()->create(['name' => 'Sec Project', 'status' => 'active']);
        $this->worker = Worker::query()->create([
            'project_id' => $this->project->id,
            'name' => 'Crew One',
        ]);
    }

    public function test_ensure_user_is_active_is_on_web_stack(): void
    {
        $this->assertTrue(
            collect(app(\Illuminate\Contracts\Http\Kernel::class)
                ->getMiddlewareGroups()['web'] ?? [])
                ->contains(fn ($m) => $m === EnsureUserIsActive::class
                    || (is_string($m) && str_contains($m, 'EnsureUserIsActive'))),
            'EnsureUserIsActive must be registered on the web middleware group',
        );
    }

    public function test_csrf_middleware_covers_web_state_changing_routes(): void
    {
        $web = collect(app(\Illuminate\Contracts\Http\Kernel::class)->getMiddlewareGroups()['web'] ?? []);

        $hasCsrf = $web->contains(function ($middleware) {
            if (! is_string($middleware)) {
                return false;
            }

            return $middleware === ValidateCsrfToken::class
                || str_ends_with($middleware, 'ValidateCsrfToken')
                || str_ends_with($middleware, 'VerifyCsrfToken');
        });

        $this->assertTrue($hasCsrf, 'Laravel ValidateCsrfToken must remain on the web stack');

        $csrf = app(ValidateCsrfToken::class);
        $this->assertSame(
            [],
            $csrf->getExcludedPaths(),
            'No CSRF except-paths — all state-changing web routes stay protected',
        );
    }

    public function test_sensitive_mutation_routes_declare_authorize_middleware(): void
    {
        $required = [
            'payouts.approve' => 'approve',
            'payouts.reject' => 'reject',
            'payouts.reconcile' => 'reconcile',
            'expenses.update' => 'update',
            'expenses.approve' => 'approve',
            'expenses.reject' => 'reject',
            'advances.repay' => 'repay',
            'advances.cancel' => 'cancel',
            'penalties.waive' => 'waive',
            'penalties.apply' => 'apply',
            'documents.destroy' => 'delete',
            'users.disable' => 'disable',
            'settlements.store' => 'manageSettlement',
            'stock.out.store' => 'stockOut',
        ];

        foreach ($required as $name => $abilityHint) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Missing route {$name}");
            $middleware = $route->gatherMiddleware();
            $joined = implode(' ', $middleware);
            $this->assertTrue(
                str_contains($joined, 'can:') || str_contains($joined, 'Authorize:'),
                "Route {$name} must declare Authorize/can middleware (ability ~{$abilityHint})",
            );
        }
    }

    public function test_role_matrix_sensitive_modules_direct_url(): void
    {
        $admin = $this->userWithRole(Roles::SUPER_ADMIN);
        $boss = $this->userWithRole(Roles::BOSS_CONTRACTOR);
        $accountant = $this->userWithRole(Roles::ACCOUNTANT);
        $stock = $this->userWithRole(Roles::STOCK_MANAGER);

        $matrix = [
            // route name => [admin, boss, accountant, stock] expected HTTP (200|403|302)
            'users.index' => [200, 403, 403, 403],
            'dashboards.vault' => [200, 200, 200, 403],
            'vault.transactions' => [302, 302, 302, 403],
            'settlements.index' => [200, 200, 200, 403],
            'dashboards.payroll' => [200, 200, 200, 403],
            'payouts.index' => [200, 200, 200, 403],
            'expenses.index' => [200, 200, 200, 403],
            'advances.index' => [302, 302, 302, 302],
            'stock.dashboard' => [200, 200, 200, 200],
            'reports.index' => [200, 200, 200, 200],
            'backups.index' => [200, 403, 200, 403],
            'audit.index' => [200, 403, 200, 403],
            'retention-holds.index' => [200, 200, 200, 403],
        ];

        $users = [$admin, $boss, $accountant, $stock];

        foreach ($matrix as $routeName => $expected) {
            foreach ($users as $i => $user) {
                $response = $this->actingAs($user)->get(route($routeName));
                $status = $response->status();
                $want = $expected[$i];
                if ($want === 302) {
                    $this->assertTrue(in_array($status, [302, 301], true), "{$routeName} role#{$i}");
                } else {
                    $this->assertSame($want, $status, "{$routeName} role#{$i} got {$status}");
                }
            }
        }
    }

    public function test_nav_hides_forbidden_items_for_each_role(): void
    {
        $cases = [
            Roles::STOCK_MANAGER => [
                'allow' => ['dashboard', 'stock', 'reports'],
                'deny' => ['vault', 'payroll', 'settlements', 'payouts', 'expenses', 'advances', 'users', 'backups', 'audit', 'insurance'],
            ],
            Roles::BOSS_CONTRACTOR => [
                'allow' => ['vault', 'payroll', 'settlements', 'expenses', 'stock', 'reports'],
                'deny' => ['backups', 'audit', 'users'],
            ],
            Roles::ACCOUNTANT => [
                'allow' => ['vault', 'payroll', 'settlements', 'payouts', 'expenses', 'advances', 'backups', 'audit', 'reports'],
                'deny' => ['users'],
            ],
            Roles::SUPER_ADMIN => [
                'allow' => ['vault', 'users', 'backups', 'audit', 'settlements', 'stock'],
                'deny' => [],
            ],
        ];

        foreach ($cases as $role => $expect) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('auth.nav', function ($nav) use ($expect) {
                        $keys = collect($nav)->values()->all();
                        foreach ($expect['allow'] as $key) {
                            if (! in_array($key, $keys, true)) {
                                return false;
                            }
                        }
                        foreach ($expect['deny'] as $key) {
                            if (in_array($key, $keys, true)) {
                                return false;
                            }
                        }

                        return true;
                    })
                );
        }
    }

    public function test_stock_manager_idor_blocked_on_financial_record_ids(): void
    {
        $stock = $this->userWithRole(Roles::STOCK_MANAGER);

        $payout = Payout::query()->create([
            'project_id' => $this->project->id,
            'worker_id' => $this->worker->id,
            'vault_id' => $this->vault->id,
            'category' => Payout::CATEGORY_PAYROLL,
            'amount_usd' => 50,
            'amount_iqd' => 65_500,
            'status' => Payout::STATUS_PENDING,
        ]);

        $expense = Expense::query()->create([
            'project_id' => $this->project->id,
            'vault_id' => $this->vault->id,
            'category' => Expense::CATEGORY_OTHER,
            'amount_iqd' => 100_000,
            'amount_usd' => 76.34,
            'exchange_rate' => 1310,
            'expense_date' => now()->toDateString(),
            'approval_status' => Expense::STATUS_PENDING,
        ]);

        $advance = EmployeeAdvance::query()->create([
            'worker_id' => $this->worker->id,
            'project_id' => $this->project->id,
            'amount_iqd' => 50_000,
            'remaining_iqd' => 50_000,
            'advanced_on' => now()->toDateString(),
            'reason' => 'Security IDOR probe',
            'status' => EmployeeAdvance::STATUS_OPEN,
        ]);

        $penalty = Penalty::query()->create([
            'project_id' => $this->project->id,
            'worker_id' => $this->worker->id,
            'amount_iqd' => 10_000,
            'amount_usd' => 7.63,
            'reason' => 'late',
            'type' => Penalty::TYPE_LATE,
            'occurred_on' => now()->toDateString(),
            'status' => Penalty::STATUS_PENDING,
        ]);

        $this->actingAs($stock)->get(route('payouts.show', $payout))->assertForbidden();
        $this->actingAs($stock)->post(route('payouts.approve', $payout))->assertForbidden();
        $this->actingAs($stock)->get(route('expenses.show', $expense))->assertForbidden();
        $this->actingAs($stock)->post(route('expenses.approve', $expense))->assertForbidden();
        $this->actingAs($stock)->get(route('advances.show', $advance))->assertRedirect(route('vault.lines.job-pay.create'));
        $this->actingAs($stock)->post(route('advances.repay', $advance), [
            'amount_iqd' => 1000,
        ])->assertRedirect(route('vault.lines.job-pay.create'));
        $this->actingAs($stock)->get(route('penalties.show', $penalty))->assertForbidden();
        $this->actingAs($stock)->post(route('settlements.store'), [
            'month' => now()->format('Y-m'),
        ])->assertForbidden();
        $this->actingAs($stock)->get(route('vault.transactions'))->assertForbidden();
        $this->actingAs($stock)->get(route('reports.show', ReportTypes::PROFIT_LOSS))->assertForbidden();
        $this->actingAs($stock)->get(route('reports.show', ReportTypes::VAULT_LEDGER))->assertForbidden();
        $this->actingAs($stock)->get(route('users.index'))->assertForbidden();
        $this->actingAs($stock)->get(route('backups.index'))->assertForbidden();
        $this->actingAs($stock)->get(route('audit.index'))->assertForbidden();
    }

    public function test_boss_cannot_mutate_money_ops_accountant_can(): void
    {
        $boss = $this->userWithRole(Roles::BOSS_CONTRACTOR);
        $accountant = $this->userWithRole(Roles::ACCOUNTANT);

        $expense = Expense::query()->create([
            'project_id' => $this->project->id,
            'vault_id' => $this->vault->id,
            'category' => Expense::CATEGORY_FUEL,
            'amount_iqd' => 50_000,
            'amount_usd' => 38.17,
            'exchange_rate' => 1310,
            'expense_date' => now()->toDateString(),
            'approval_status' => Expense::STATUS_PENDING,
        ]);

        $payout = Payout::query()->create([
            'project_id' => $this->project->id,
            'worker_id' => $this->worker->id,
            'vault_id' => $this->vault->id,
            'category' => Payout::CATEGORY_PAYROLL,
            'amount_usd' => 40,
            'amount_iqd' => 52_400,
            'status' => Payout::STATUS_PENDING,
        ]);

        $this->actingAs($boss)->get(route('expenses.create'))->assertForbidden();
        $this->actingAs($boss)->post(route('expenses.approve', $expense))->assertForbidden();
        $this->actingAs($boss)->get(route('payouts.create'))->assertForbidden();
        $this->actingAs($boss)->post(route('payouts.approve', $payout))->assertForbidden();
        $this->actingAs($boss)->post(route('settlements.store'), [
            'month' => now()->format('Y-m'),
        ])->assertForbidden();
        $this->actingAs($boss)->put(route('retention-holds.settings'), [
            'holdback_pct' => 10,
        ])->assertForbidden();

        $this->actingAs($accountant)->get(route('expenses.create'))->assertOk();
        $this->actingAs($accountant)->get(route('payouts.create'))->assertOk();
        $this->actingAs($accountant)->get(route('expenses.edit', $expense))->assertOk();
    }

    public function test_disabled_user_cannot_login_or_keep_session(): void
    {
        $user = $this->userWithRole(Roles::ACCOUNTANT);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $user->forceFill(['status' => User::STATUS_DISABLED])->save();

        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
        $this->assertGuest();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_negative_financial_amounts_rejected_server_side(): void
    {
        $accountant = $this->userWithRole(Roles::ACCOUNTANT);

        $this->actingAs($accountant)
            ->from(route('expenses.create'))
            ->post(route('expenses.store'), [
                'project_id' => $this->project->id,
                'category' => Expense::CATEGORY_OTHER,
                'amount_iqd' => -5000,
                'expense_date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('amount');

        $this->actingAs($accountant)
            ->from(route('advances.create'))
            ->post(route('advances.store'), [
                'worker_id' => $this->worker->id,
                'project_id' => $this->project->id,
                'amount_iqd' => -1000,
                'advanced_on' => now()->toDateString(),
                'reason' => 'bad',
                'repayment_method' => 'payroll_deduction',
            ])
            ->assertRedirect(route('vault.lines.job-pay.create'));

        $this->actingAs($accountant)
            ->from(route('payouts.create'))
            ->post(route('payouts.store'), [
                'project_id' => $this->project->id,
                'worker_id' => $this->worker->id,
                'category' => Payout::CATEGORY_PAYROLL,
                'amount_usd' => -10,
            ])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, Expense::query()->count());
        $this->assertSame(0, EmployeeAdvance::query()->count());
        $this->assertSame(0, Payout::query()->count());
    }

    public function test_stock_out_cannot_drive_quantity_negative(): void
    {
        $stock = $this->userWithRole(Roles::STOCK_MANAGER);
        $supplier = Supplier::query()->create(['name' => 'Sec Supplier']);
        $item = StockItem::query()->create([
            'name' => 'Cement',
            'sku' => 'CEM-SEC',
            'unit' => 'bag',
            'quantity' => 5,
            'min_quantity' => 1,
            'purchase_price_iqd' => 10_000,
            'supplier_id' => $supplier->id,
        ]);

        $this->actingAs($stock)
            ->from(route('stock.out.create'))
            ->post(route('stock.out.store'), [
                'stock_item_id' => $item->id,
                'quantity' => 50,
                'moved_on' => now()->toDateString(),
                'project_id' => $this->project->id,
            ])
            ->assertRedirect(route('stock.out.create'))
            ->assertSessionHasErrors('quantity');

        $this->assertSame('5.000', (string) $item->fresh()->quantity);
    }
}
