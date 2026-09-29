<?php

namespace Tests\Feature;

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

/**
 * Phase 3 — login as each seeded role and assert allowed / denied routes.
 */
class RoleAccessPhase3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(UserSeeder::class);
        $this->seed(DemoUsersSeeder::class);
        Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 5000,
            'balance_iqd' => 6550000,
        ]);
    }

    public function test_seed_users_can_log_in(): void
    {
        foreach ([
            UserSeeder::ADMIN_EMAIL => Roles::SUPER_ADMIN,
            DemoUsersSeeder::BOSS_EMAIL => Roles::BOSS_CONTRACTOR,
            DemoUsersSeeder::ACCOUNTANT_EMAIL => Roles::ACCOUNTANT,
            DemoUsersSeeder::STOCK_EMAIL => Roles::STOCK_MANAGER,
        ] as $email => $role) {
            $user = User::query()->where('email', $email)->first();
            $this->assertNotNull($user, "Missing seed user {$email}");
            $this->assertTrue($user->hasRole($role));

            $this->post('/login', [
                'email' => $email,
                'password' => 'password',
            ])->assertRedirect(route('dashboard'));

            $this->assertAuthenticatedAs($user);
            $this->post('/logout');
            $this->assertGuest();
        }
    }

    public function test_super_admin_reaches_all_existing_modules(): void
    {
        $admin = User::query()->where('email', UserSeeder::ADMIN_EMAIL)->firstOrFail();

        foreach ([
            'dashboard',
            'dashboards.vault',
            'dashboards.payroll',
            'projects.index',
            'workers.index',
            'payouts.index',
            'payouts.create',
            'penalties.index',
            'retention-holds.index',
            'documents.index',
            'imports.index',
            'reports.index',
            'backups.index',
            'audit.index',
            'users.index',
        ] as $name) {
            $this->actingAs($admin)->get(route($name))->assertOk();
        }

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('roleHome', Roles::SUPER_ADMIN)
                ->has('summary.available_iqd')
                ->has('summary.available_usd')
                ->has('summary.charts')
                ->has('summary.projects')
            );
    }

    public function test_boss_sees_business_modules_not_system_infra(): void
    {
        $boss = User::query()->where('email', DemoUsersSeeder::BOSS_EMAIL)->firstOrFail();

        foreach ([
            'dashboard',
            'dashboards.vault',
            'dashboards.payroll',
            'projects.index',
            'workers.index',
            'payouts.index',
            'penalties.index',
            'retention-holds.index',
            'documents.index',
            'imports.index',
            'reports.index',
        ] as $name) {
            $this->actingAs($boss)->get(route($name))->assertOk();
        }

        $this->actingAs($boss)->get(route('payouts.create'))->assertForbidden();
        $this->actingAs($boss)->get(route('backups.index'))->assertForbidden();
        $this->actingAs($boss)->get(route('audit.index'))->assertForbidden();
        $this->actingAs($boss)->get(route('users.index'))->assertForbidden();

        $this->actingAs($boss)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('roleHome', Roles::BOSS_CONTRACTOR)
                ->has('summary.available_iqd')
                ->has('summary.available_usd')
                ->has('summary.charts')
                ->has('summary.unclassified_people')
                ->missing('summary.workbook_bundled')
                ->where('auth.role', Roles::BOSS_CONTRACTOR)
            );
    }

    public function test_accountant_money_ops_without_user_admin_or_project_create(): void
    {
        $accountant = User::query()->where('email', DemoUsersSeeder::ACCOUNTANT_EMAIL)->firstOrFail();

        foreach ([
            'dashboard',
            'dashboards.vault',
            'dashboards.payroll',
            'projects.index',
            'workers.index',
            'payouts.index',
            'payouts.create',
            'penalties.index',
            'retention-holds.index',
            'documents.index',
            'imports.index',
            'reports.index',
            'backups.index',
            'audit.index',
        ] as $name) {
            $this->actingAs($accountant)->get(route($name))->assertOk();
        }

        $this->actingAs($accountant)->get(route('projects.create'))->assertForbidden();
        $this->actingAs($accountant)->get(route('workers.create'))->assertForbidden();
        $this->actingAs($accountant)->get(route('users.index'))->assertForbidden();

        $this->actingAs($accountant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('roleHome', Roles::ACCOUNTANT)
                ->has('summary.pending_payouts')
            );
    }

    public function test_stock_manager_dashboard_only_and_business_routes_forbidden(): void
    {
        $stock = User::query()->where('email', DemoUsersSeeder::STOCK_EMAIL)->firstOrFail();

        // Harden: even if a Worker row was linked, abilities stay role-based (empty).
        Worker::query()->create([
            'user_id' => $stock->id,
            'name' => 'Should Not Grant Access',
        ]);

        $this->actingAs($stock)->get(route('dashboard'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('roleHome', Roles::STOCK_MANAGER)
                ->has('summary.total_items')
                ->where('auth.nav', function ($nav) {
                    $keys = collect($nav)->values()->all();

                    return in_array('dashboard', $keys, true)
                        && in_array('stock', $keys, true)
                        && ! in_array('vault', $keys, true);
                })
            );

        $this->actingAs($stock)->get(route('stock.dashboard'))->assertOk();
        $this->actingAs($stock)->get(route('reports.index'))->assertOk();

        foreach ([
            'dashboards.vault',
            'dashboards.payroll',
            'projects.index',
            'workers.index',
            'payouts.index',
            'payouts.create',
            'penalties.index',
            'retention-holds.index',
            'documents.index',
            'imports.index',
            'backups.index',
            'audit.index',
            'users.index',
        ] as $name) {
            $this->actingAs($stock)->get(route($name))->assertForbidden();
        }

        $this->assertSame(5, $stock->fresh()->getAllPermissions()->count());
    }

    public function test_demo_users_seeder_clears_stock_worker_link(): void
    {
        $stock = User::query()->where('email', DemoUsersSeeder::STOCK_EMAIL)->firstOrFail();
        Worker::query()->create([
            'user_id' => $stock->id,
            'name' => 'Linked By Mistake',
        ]);

        $this->seed(DemoUsersSeeder::class);

        $this->assertFalse(
            Worker::query()->where('user_id', $stock->id)->exists()
        );
    }
}
