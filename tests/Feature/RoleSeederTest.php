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
    }
}
