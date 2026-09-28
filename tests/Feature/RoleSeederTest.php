<?php

namespace Tests\Feature;

use App\Support\Permissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_seeder_creates_exactly_four_blueprint_roles(): void
    {
        $this->seed(RoleSeeder::class);

        $this->assertSame(4, Role::query()->count());

        foreach (RoleSeeder::ROLES as $role) {
            $this->assertTrue(
                Role::where('name', $role)->where('guard_name', 'web')->exists(),
                "Missing role: {$role}",
            );
        }
    }

    public function test_role_seeder_creates_permissions_and_syncs_matrix(): void
    {
        $this->seed(RoleSeeder::class);

        $this->assertSame(count(Permissions::ALL), Permission::query()->count());

        foreach (Permissions::ALL as $name) {
            $this->assertTrue(
                Permission::where('name', $name)->where('guard_name', 'web')->exists(),
                "Missing permission: {$name}",
            );
        }

        $accountant = Role::findByName('Accountant', 'web');
        $this->assertTrue($accountant->hasPermissionTo(Permissions::VAULT_VIEW));
        $this->assertTrue($accountant->hasPermissionTo(Permissions::PAYOUTS_CREATE));
        $this->assertFalse($accountant->hasPermissionTo(Permissions::PROJECTS_CREATE));
        $this->assertFalse($accountant->hasPermissionTo(Permissions::WORKERS_CREATE));

        $boss = Role::findByName('Boss / Contractor', 'web');
        $this->assertTrue($boss->hasPermissionTo(Permissions::VAULT_VIEW));
        $this->assertTrue($boss->hasPermissionTo(Permissions::VAULT_PAYROLL));
        $this->assertTrue($boss->hasPermissionTo(Permissions::WORKERS_CREATE));
        $this->assertFalse($boss->hasPermissionTo(Permissions::PAYOUTS_CREATE));
        $this->assertFalse($boss->hasPermissionTo(Permissions::VAULT_BACKUPS));

        $stock = Role::findByName('Stock Manager', 'web');
        $this->assertSame(5, $stock->permissions()->count());
        $this->assertTrue($stock->hasPermissionTo(Permissions::STOCK_VIEW_ANY));
        $this->assertTrue($stock->hasPermissionTo(Permissions::STOCK_MANAGE_ITEMS));
        $this->assertFalse($stock->hasPermissionTo(Permissions::WORKERS_VIEW_ANY));
        $this->assertFalse($stock->hasPermissionTo(Permissions::PAYOUTS_VIEW_ANY));
        $this->assertFalse($stock->hasPermissionTo(Permissions::VAULT_VIEW));
    }

    public function test_role_seeder_renames_legacy_roles_keeping_ids(): void
    {
        $legacyEngineer = Role::create(['name' => 'Site Engineer', 'guard_name' => 'web']);
        $legacyWorker = Role::create(['name' => 'Worker', 'guard_name' => 'web']);
        $engineerId = $legacyEngineer->id;
        $workerId = $legacyWorker->id;

        $this->seed(RoleSeeder::class);

        $this->assertDatabaseHas('roles', ['id' => $engineerId, 'name' => 'Boss / Contractor']);
        $this->assertDatabaseHas('roles', ['id' => $workerId, 'name' => 'Stock Manager']);
        $this->assertDatabaseMissing('roles', ['name' => 'Site Engineer']);
        $this->assertDatabaseMissing('roles', ['name' => 'Worker']);
        $this->assertSame(4, Role::query()->count());
    }
}
