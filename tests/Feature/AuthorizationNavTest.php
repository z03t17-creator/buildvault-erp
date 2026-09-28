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

        $worker = Role::findByName(Roles::WORKER, 'web');
        $this->assertFalse($worker->hasPermissionTo(Permissions::VAULT_VIEW));
        $this->assertFalse($worker->hasPermissionTo(Permissions::VAULT_PAYROLL));
        $this->assertFalse($worker->hasPermissionTo(Permissions::VAULT_BACKUPS));
        $this->assertTrue($worker->hasPermissionTo(Permissions::ATTENDANCE_VIEW_ANY));
    }

    public function test_worker_cannot_hit_vault_or_payroll_admin_routes(): void
    {
        $worker = $this->userWithRole(Roles::WORKER);

        $this->actingAs($worker)->get(route('dashboards.vault'))->assertForbidden();
        $this->actingAs($worker)->get(route('dashboards.payroll'))->assertForbidden();
        $this->actingAs($worker)->get(route('backups.index'))->assertForbidden();
        $this->actingAs($worker)->get(route('audit.index'))->assertForbidden();
        $this->actingAs($worker)->get(route('imports.index'))->assertForbidden();
        $this->actingAs($worker)->get(route('exports.index'))->assertForbidden();
        $this->actingAs($worker)->get(route('retention-holds.index'))->assertForbidden();
        $this->actingAs($worker)->get(route('workers.index'))->assertForbidden();
        $this->actingAs($worker)->get(route('penalties.index'))->assertForbidden();
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

    public function test_worker_nav_prop_does_not_leak_forbidden_links(): void
    {
        $worker = $this->userWithRole(Roles::WORKER);

        $this->actingAs($worker)
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
                        'workers',
                        'penalties',
                        'imports',
                        'exports',
                        'backups',
                        'audit',
                        'insurance',
                    ];
                    foreach ($forbidden as $key) {
                        if (in_array($key, $keys, true)) {
                            return false;
                        }
                    }

                    return in_array('dashboard', $keys, true)
                        && in_array('attendance', $keys, true);
                })
                ->where('auth.can', function ($can) {
                    return ($can['vault.view'] ?? null) === false
                        && ($can['vault.payroll'] ?? null) === false
                        && ($can['vault.backups'] ?? null) === false
                        && ($can['workers.viewAny'] ?? null) === false;
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
                        'penalties',
                        'docs',
                        'imports',
                        'exports',
                        'backups',
                        'audit',
                        'insurance',
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
                        && ($can['projects.delete'] ?? null) === true;
                })
            );
    }

    public function test_engineer_nav_hides_vault_money_and_backups(): void
    {
        $engineer = $this->userWithRole(Roles::SITE_ENGINEER);

        $this->actingAs($engineer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.nav', function ($nav) {
                    $keys = collect($nav)->values()->all();

                    return ! in_array('vault', $keys, true)
                        && ! in_array('backups', $keys, true)
                        && ! in_array('audit', $keys, true)
                        && ! in_array('insurance', $keys, true)
                        && in_array('payroll', $keys, true)
                        && in_array('workers', $keys, true)
                        && in_array('projects', $keys, true);
                })
                ->where('auth.can', function ($can) {
                    return ($can['vault.view'] ?? null) === false
                        && ($can['workers.create'] ?? null) === true
                        && ($can['payouts.create'] ?? null) === false;
                })
            );
    }
}
