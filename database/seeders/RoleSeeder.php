<?php

namespace Database\Seeders;

use App\Support\Permissions;
use App\Support\Roles;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * BuildVault ERP roles (blueprint naming).
     *
     * @var list<string>
     */
    public const ROLES = Roles::ALL;

    /**
     * Seed roles + Spatie permissions; Super Admin gets every permission.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (Permissions::ALL as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (self::ROLES as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        $superAdmin = Role::findByName(Roles::SUPER_ADMIN, 'web');
        $superAdmin->syncPermissions(Permissions::ALL);

        foreach (Permissions::matrix() as $roleName => $permissionNames) {
            Role::findByName($roleName, 'web')->syncPermissions($permissionNames);
        }
    }
}
