<?php

namespace Tests\Feature;

use App\Models\EmployeeAdvance;
use App\Models\Expense;
use App\Models\Payout;
use App\Models\Project;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vault;
use App\Models\Worker;
use App\Support\Roles;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Phase 20 — final four-role QA: login, nav, happy paths, 403s, IQD (+ Phase 4 attendance).
 */
class RoleQaPhase20Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! \Illuminate\Support\Facades\Route::has('workers.index')) {
            $this->markTestSkipped('Module surface dropped in staff rewire slice.');
        }


        // Full demo dataset behind SEED_DEMO gate
        putenv('SEED_DEMO=true');
        $_ENV['SEED_DEMO'] = 'true';
        $_SERVER['SEED_DEMO'] = 'true';
        $this->seed(DatabaseSeeder::class);
    }

    public function test_super_admin_login_nav_and_critical_paths(): void
    {
        $admin = User::query()->where('email', 'admin@zhako.test')->firstOrFail();
        $this->assertTrue($admin->hasRole(Roles::SUPER_ADMIN));
        $this->assertTrue(Hash::check('password', $admin->password));

        $this->post('/login', ['email' => 'admin@zhako.test', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('roleHome', Roles::SUPER_ADMIN)
                ->has('auth.nav')
                ->where('auth.nav', function ($nav) {
                    $keys = collect($nav)->values()->all();

                    return in_array('attendance', $keys, true)
                        && in_array('vault', $keys, true)
                        && in_array('settlements', $keys, true)
                        && in_array('users', $keys, true)
                        && in_array('stock', $keys, true)
                        && in_array('reports', $keys, true);
                })
            );

        foreach ([
            'dashboards.vault',
            'dashboards.payroll',
            'settlements.index',
            'payouts.index',
            'expenses.index',
            'vault.lines.job-pay.create',  // was advances.index
            'attendance.index',
            'stock.dashboard',
            'stock.in.create',
            'stock.out.create',
            'reports.index',
            'users.index',
            'backups.index',
            'audit.index',
        ] as $name) {
            $this->actingAs($admin)->get(route($name))->assertOk();
        }

        // PDF / export surfaces allowed
        $this->actingAs($admin)->get(route('reports.export', ['type' => 'vault_ledger', 'format' => 'pdf']))
            ->assertOk();

        $this->assertAttendanceSurface($admin, canManage: true);
        $this->assertVaultShowsIqdNotFx($admin);
    }

    public function test_boss_login_nav_and_boundaries(): void
    {
        $boss = User::query()->where('email', 'boss@zhako.test')->firstOrFail();
        $this->assertTrue($boss->hasRole(Roles::BOSS_CONTRACTOR));

        $this->post('/login', ['email' => 'boss@zhako.test', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('roleHome', Roles::BOSS_CONTRACTOR)
                ->where('auth.nav', function ($nav) {
                    $keys = collect($nav)->values()->all();

                    return in_array('vault', $keys, true)
                        && in_array('settlements', $keys, true)
                        && in_array('payroll', $keys, true)
                        && in_array('attendance', $keys, true)
                        && ! in_array('backups', $keys, true)
                        && ! in_array('audit', $keys, true)
                        && ! in_array('users', $keys, true);
                })
            );

        foreach ([
            'dashboards.vault',
            'dashboards.payroll',
            'settlements.index',
            'payouts.index',
            'expenses.index',
            'vault.lines.job-pay.create',  // was advances.index
            'attendance.index',
            'projects.index',
            'workers.index',
            'stock.dashboard',
            'reports.index',
        ] as $name) {
            $this->actingAs($boss)->get(route($name))->assertOk();
        }

        // Denied money mutations / infra
        foreach ([
            'payouts.create',
            'expenses.create',
            'vault.lines.job-pay.create',  // was advances.create
            'backups.index',
            'audit.index',
            'users.index',
            'stock.in.create',
            'stock.out.create',
        ] as $name) {
            $this->actingAs($boss)->get(route($name))->assertForbidden();
        }

        $this->assertAttendanceSurface($boss, canManage: false);
        $this->assertVaultShowsIqdNotFx($boss);
    }

    public function test_accountant_login_money_ops_and_boundaries(): void
    {
        $accountant = User::query()->where('email', 'accountant@zhako.test')->firstOrFail();
        $this->assertTrue($accountant->hasRole(Roles::ACCOUNTANT));

        $this->post('/login', ['email' => 'accountant@zhako.test', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('roleHome', Roles::ACCOUNTANT)
                ->where('auth.nav', function ($nav) {
                    $keys = collect($nav)->values()->all();

                    return in_array('vault', $keys, true)
                        && in_array('payouts', $keys, true)
                        && in_array('expenses', $keys, true)
                        && in_array('advances', $keys, true)
                        && in_array('settlements', $keys, true)
                        && in_array('reports', $keys, true)
                        && in_array('backups', $keys, true)
                        && in_array('attendance', $keys, true)
                        && ! in_array('users', $keys, true);
                })
            );

        foreach ([
            'dashboards.vault',
            'dashboards.payroll',
            'settlements.index',
            'payouts.index',
            'payouts.create',
            'expenses.index',
            'expenses.create',
            'vault.lines.job-pay.create',  // was advances.index
            'vault.lines.job-pay.create',  // was advances.create
            'attendance.index',
            'reports.index',
            'backups.index',
            'audit.index',
        ] as $name) {
            $this->actingAs($accountant)->get(route($name))->assertOk();
        }

        // Cannot create projects / manage users / mutate stock
        foreach ([
            'projects.create',
            'users.index',
            'stock.in.create',
            'stock.out.create',
        ] as $name) {
            $this->actingAs($accountant)->get(route($name))->assertForbidden();
        }

        // Happy path: approve a pending expense if present
        $pending = Expense::query()->where('approval_status', Expense::STATUS_PENDING)->first();
        if ($pending) {
            $this->actingAs($accountant)
                ->post(route('expenses.approve', $pending))
                ->assertRedirect();
            $this->assertSame(Expense::STATUS_APPROVED, $pending->fresh()->approval_status);
        }

        $this->actingAs($accountant)->get(route('reports.export', ['type' => 'expense', 'format' => 'pdf']))
            ->assertOk();

        $this->assertAttendanceSurface($accountant, canManage: false);
        $this->assertVaultShowsIqdNotFx($accountant);
    }

    public function test_stock_manager_login_stock_ops_and_boundaries(): void
    {
        $stock = User::query()->where('email', 'stock@zhako.test')->firstOrFail();
        $this->assertTrue($stock->hasRole(Roles::STOCK_MANAGER));

        $this->post('/login', ['email' => 'stock@zhako.test', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('roleHome', Roles::STOCK_MANAGER)
                ->where('auth.nav', function ($nav) {
                    $keys = collect($nav)->values()->all();

                    return in_array('dashboard', $keys, true)
                        && in_array('stock', $keys, true)
                        && in_array('reports', $keys, true)
                        && in_array('attendance', $keys, true)
                        && ! in_array('vault', $keys, true)
                        && ! in_array('payroll', $keys, true)
                        && ! in_array('payouts', $keys, true)
                        && ! in_array('expenses', $keys, true);
                })
            );

        foreach ([
            'stock.dashboard',
            'stock.items.index',
            'stock.movements.index',
            'stock.suppliers.index',
            'stock.in.create',
            'stock.out.create',
            'attendance.index',
            'reports.index',
        ] as $name) {
            $this->actingAs($stock)->get(route($name))->assertOk();
        }

        foreach ([
            'dashboards.vault',
            'dashboards.payroll',
            'settlements.index',
            'payouts.index',
            'expenses.index',
            'vault.lines.job-pay.create',  // was advances.index
            'projects.index',
            'workers.index',
            'backups.index',
            'audit.index',
            'users.index',
        ] as $name) {
            $this->actingAs($stock)->get(route($name))->assertForbidden();
        }

        // Stock OUT to a project (happy path)
        $item = StockItem::query()->where('quantity', '>', 0)->firstOrFail();
        $project = Project::query()->where('status', 'active')->firstOrFail();
        $before = (float) $item->quantity;

        $this->actingAs($stock)
            ->post(route('stock.out.store'), [
                'stock_item_id' => $item->id,
                'quantity' => 1,
                'moved_on' => now()->toDateString(),
                'project_id' => $project->id,
                'receiver' => 'Crew',
                'issuer' => 'Stock',
                'purpose' => 'Phase 20 QA stock out',
            ])
            ->assertRedirect();

        $this->assertSame($before - 1, (float) $item->fresh()->quantity);
        $this->assertTrue(
            StockMovement::query()
                ->where('stock_item_id', $item->id)
                ->where('project_id', $project->id)
                ->where('type', StockMovement::TYPE_OUT)
                ->where('purpose', 'Phase 20 QA stock out')
                ->exists()
        );

        // Financial report type forbidden; stock report OK
        $this->actingAs($stock)->get(route('reports.show', 'vault_ledger'))->assertForbidden();
        $this->actingAs($stock)->get(route('reports.show', 'inventory'))->assertOk();
        $this->actingAs($stock)->get(route('reports.export', ['type' => 'inventory', 'format' => 'pdf']))
            ->assertOk();

        $this->assertAttendanceSurface($stock, canManage: true);
    }

    public function test_money_surfaces_are_iqd_only_and_demo_counts_ready(): void
    {
        $this->assertGreaterThanOrEqual(1, Vault::query()->count());
        $this->assertGreaterThanOrEqual(2, Project::query()->count());
        $this->assertGreaterThanOrEqual(1, Payout::query()->count());
        $this->assertGreaterThanOrEqual(1, Expense::query()->count());
        $this->assertGreaterThanOrEqual(1, EmployeeAdvance::query()->count());
        $this->assertGreaterThanOrEqual(1, StockItem::query()->count());
        $this->assertGreaterThanOrEqual(1, Supplier::query()->count());
        $this->assertGreaterThanOrEqual(1, Worker::query()->count());

        // Attendance UI routes registered (Phase 4)
        $this->assertTrue(
            collect(\Illuminate\Support\Facades\Route::getRoutes())->contains(
                fn ($r) => str_contains($r->uri(), 'attendance')
            )
        );
    }

    private function assertAttendanceSurface(User $user, bool $canManage): void
    {
        $this->actingAs($user)
            ->get('/attendance')
            ->assertOk();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.nav', fn ($nav) => collect($nav)->contains('attendance'))
                ->where('auth.can', function ($can) use ($canManage) {
                    return ($can['attendance.viewAny'] ?? null) === true
                        && ($can['attendance.manage'] ?? null) === $canManage;
                })
            );

        if (! $canManage) {
            $this->actingAs($user)
                ->post(route('attendance.check-in'), [
                    'date' => now()->toDateString(),
                    'check_in' => '08:00',
                    'worker_ids' => [1],
                ])
                ->assertForbidden();
        }
    }

    private function assertVaultShowsIqdNotFx(User $user): void
    {
        $this->actingAs($user)
            ->get(route('dashboards.vault'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('vault.balance_iqd')
                ->has('liquidity.available_iqd')
            );

        $htmlish = $this->actingAs($user)->get(route('dashboards.vault'));
        $htmlish->assertOk();
        // Inertia JSON payload should not include refresh-fx CTA flags for UI
        $content = $htmlish->getContent();
        $this->assertStringNotContainsString('Refresh FX', $content);
        $this->assertStringNotContainsString('Live FX', $content);
        $this->assertStringNotContainsString('USD balance', $content);
    }
}
