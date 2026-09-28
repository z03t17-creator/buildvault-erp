<?php

namespace Tests\Feature;

use App\Support\Permissions;
use App\Support\Roles;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthorizationNavTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_seeder_gives_super_admin_every_permission(): void
    {
        $this->seed(RoleSeeder::class);

        $this->assertSame(count(Permissions::ALL), Permission::query()->count());

        $superAdmin = Role::findByName(Roles::SUPER_ADMIN, 'web');
        foreach (Permissions::ALL as $permission) {
            $this->assertTrue(
                $superAdmin->hasPermissionTo($permission),
                "Super Admin missing permission: {$permission}",
            );
        }

        $stock = Role::findByName(Roles::STOCK_MANAGER, 'web');
        $this->assertFalse($stock->hasPermissionTo(Permissions::VAULT_VIEW));
        $this->assertFalse($stock->hasPermissionTo(Permissions::VAULT_PAYROLL));
        $this->assertFalse($stock->hasPermissionTo(Permissions::VAULT_BACKUPS));
        $this->assertFalse($stock->hasPermissionTo(Permissions::ATTENDANCE_VIEW_ANY));
        $this->assertFalse($stock->hasPermissionTo(Permissions::PAYOUTS_VIEW_ANY));
        $this->assertSame(0, $stock->permissions()->count());
    }

    public function test_stock_manager_cannot_hit_vault_or_payroll_admin_routes(): void
    {
        $stock = $this->userWithRole(Roles::STOCK_MANAGER);

        $this->actingAs($stock)->get(route('dashboards.vault'))->assertForbidden();
        $this->actingAs($stock)->get(route('dashboards.payroll'))->assertForbidden();
        $this->actingAs($stock)->get(route('backups.index'))->assertForbidden();
        $this->actingAs($stock)->get(route('audit.index'))->assertForbidden();
        $this->actingAs($stock)->get(route('imports.index'))->assertForbidden();
        $this->actingAs($stock)->get(route('exports.index'))->assertForbidden();
        $this->actingAs($stock)->get(route('retention-holds.index'))->assertForbidden();
        $this->actingAs($stock)->get(route('workers.index'))->assertForbidden();
        $this->actingAs($stock)->get(route('penalties.index'))->assertForbidden();
        $this->actingAs($stock)->get(route('projects.index'))->assertForbidden();
        $this->actingAs($stock)->get(route('payouts.index'))->assertForbidden();
        $this->actingAs($stock)->get(route('expenses.index'))->assertForbidden();
        $this->actingAs($stock)->get(route('advances.index'))->assertForbidden();
        $this->actingAs($stock)->get(route('productions.index'))->assertForbidden();
        $this->actingAs($stock)->get(route('attendance.index'))->assertForbidden();
    }

    public function test_super_admin_can_hit_sensitive_routes(): void
    {
        $admin = $this->userWithRole(Roles::SUPER_ADMIN);

        $this->actingAs($admin)->get(route('dashboards.vault'))->assertOk();
        $this->actingAs($admin)->get(route('dashboards.payroll'))->assertOk();
        $this->actingAs($admin)->get(route('backups.index'))->assertOk();
        $this->actingAs($admin)->get(route('audit.index'))->assertOk();
        $this->actingAs($admin)->get(route('imports.index'))->assertOk();
        $this->actingAs($admin)->get(route('exports.index'))->assertOk();
        $this->actingAs($admin)->get(route('retention-holds.index'))->assertOk();
        $this->actingAs($admin)->get(route('workers.index'))->assertOk();
        $this->actingAs($admin)->get(route('payouts.index'))->assertOk();
    }

    public function test_stock_manager_nav_prop_is_dashboard_only(): void
    {
        $stock = $this->userWithRole(Roles::STOCK_MANAGER);

        $this->actingAs($stock)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('auth.nav')
                ->has('auth.can')
                ->where('auth.nav', function ($nav) {
                    $keys = collect($nav)->values()->all();
                    $forbidden = [
                        'vault',
                        'payroll',
                        'projects',
                        'workers',
                        'attendance',
                        'payouts',
                        'expenses',
                        'penalties',
                        'advances',
                        'productions',
                        'docs',
                        'imports',
                        'exports',
                        'backups',
                        'audit',
                        'insurance',
                        'users',
                    ];
                    foreach ($forbidden as $key) {
                        if (in_array($key, $keys, true)) {
                            return false;
                        }
                    }

                    return $keys === ['dashboard'];
                })
                ->where('auth.can', function ($can) {
                    return ($can['vault.view'] ?? null) === false
                        && ($can['vault.payroll'] ?? null) === false
                        && ($can['vault.backups'] ?? null) === false
                        && ($can['workers.viewAny'] ?? null) === false
                        && ($can['projects.viewAny'] ?? null) === false
                        && ($can['users.viewAny'] ?? null) === false
                        && ($can['advances.viewAny'] ?? null) === false
                        && ($can['productions.viewAny'] ?? null) === false;
                })
            );
    }

    public function test_super_admin_nav_includes_all_primary_admin_links(): void
    {
        $admin = $this->userWithRole(Roles::SUPER_ADMIN);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.nav', function ($nav) {
                    $keys = collect($nav)->values()->all();
                    foreach ([
                        'dashboard',
                        'vault',
                        'payroll',
                        'projects',
                        'workers',
                        'attendance',
                        'payouts',
                        'expenses',
                        'penalties',
                        'advances',
                        'productions',
                        'docs',
                        'imports',
                        'exports',
                        'backups',
                        'audit',
                        'insurance',
                        'users',
                    ] as $key) {
                        if (! in_array($key, $keys, true)) {
                            return false;
                        }
                    }

                    return true;
                })
                ->where('auth.can', function ($can) {
                    return ($can['vault.view'] ?? null) === true
                        && ($can['vault.backups'] ?? null) === true
                        && ($can['projects.delete'] ?? null) === true
                        && ($can['users.viewAny'] ?? null) === true
                        && ($can['productions.viewAny'] ?? null) === true;
                })
            );
    }

    public function test_boss_contractor_nav_shows_vault_hides_backups_and_audit(): void
    {
        $boss = $this->userWithRole(Roles::BOSS_CONTRACTOR);

        $this->actingAs($boss)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.nav', function ($nav) {
                    $keys = collect($nav)->values()->all();

                    return in_array('vault', $keys, true)
                        && in_array('payroll', $keys, true)
                        && in_array('insurance', $keys, true)
                        && in_array('workers', $keys, true)
                        && in_array('projects', $keys, true)
                        && in_array('payouts', $keys, true)
                        && in_array('expenses', $keys, true)
                        && in_array('advances', $keys, true)
                        && in_array('productions', $keys, true)
                        && in_array('exports', $keys, true)
                        && ! in_array('backups', $keys, true)
                        && ! in_array('audit', $keys, true)
                        && ! in_array('users', $keys, true);
                })
                ->where('auth.can', function ($can) {
                    return ($can['vault.view'] ?? null) === true
                        && ($can['vault.payroll'] ?? null) === true
                        && ($can['vault.retention'] ?? null) === true
                        && ($can['vault.retentionManage'] ?? null) === false
                        && ($can['workers.create'] ?? null) === true
                        && ($can['payouts.create'] ?? null) === false
                        && ($can['expenses.create'] ?? null) === false
                        && ($can['expenses.viewAny'] ?? null) === true
                        && ($can['advances.viewAny'] ?? null) === true
                        && ($can['advances.create'] ?? null) === false
                        && ($can['productions.viewAny'] ?? null) === true
                        && ($can['productions.create'] ?? null) === false
                        && ($can['penalties.viewAny'] ?? null) === true
                        && ($can['penalties.create'] ?? null) === false
                        && ($can['vault.backups'] ?? null) === false
                        && ($can['vault.audit'] ?? null) === false
                        && ($can['users.viewAny'] ?? null) === false;
                })
                ->where('auth.role', Roles::BOSS_CONTRACTOR)
                ->where('roleHome', Roles::BOSS_CONTRACTOR)
            );
    }

    public function test_accountant_nav_includes_backups_audit_and_money_ops(): void
    {
        $accountant = $this->userWithRole(Roles::ACCOUNTANT);

        $this->actingAs($accountant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.nav', function ($nav) {
                    $keys = collect($nav)->values()->all();

                    return in_array('vault', $keys, true)
                        && in_array('payroll', $keys, true)
                        && in_array('payouts', $keys, true)
                        && in_array('expenses', $keys, true)
                        && in_array('advances', $keys, true)
                        && in_array('productions', $keys, true)
                        && in_array('projects', $keys, true)
                        && in_array('backups', $keys, true)
                        && in_array('audit', $keys, true)
                        && in_array('insurance', $keys, true);
                })
                ->where('auth.can', function ($can) {
                    return ($can['payouts.create'] ?? null) === true
                        && ($can['expenses.create'] ?? null) === true
                        && ($can['expenses.approve'] ?? null) === true
                        && ($can['advances.create'] ?? null) === true
                        && ($can['advances.repay'] ?? null) === true
                        && ($can['productions.create'] ?? null) === true
                        && ($can['productions.update'] ?? null) === true
                        && ($can['projects.create'] ?? null) === false
                        && ($can['workers.create'] ?? null) === false
                        && ($can['vault.backups'] ?? null) === true;
                })
                ->where('roleHome', Roles::ACCOUNTANT)
            );
    }
}
