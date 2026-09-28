<?php

namespace Database\Seeders;

use App\Support\Permissions;
use App\Support\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * BuildVault ERP roles (Phase 2 business naming).
     *
     * @var list<string>
     */
    public const ROLES = Roles::ALL;

    /**
     * Seed roles + Spatie permissions; Super Admin gets every permission.
     * Safely renames legacy Site Engineer / Worker rows (same IDs) when present.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->renameLegacyRoles();

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

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * UPDATE roles.name keeping IDs so model_has_roles stays valid. No migrate:fresh.
     */
    private function renameLegacyRoles(): void
    {
        $map = [
            Roles::LEGACY_SITE_ENGINEER => Roles::BOSS_CONTRACTOR,
            Roles::LEGACY_WORKER => Roles::STOCK_MANAGER,
        ];

        foreach ($map as $from => $to) {
            $legacy = Role::query()->where('name', $from)->where('guard_name', 'web')->first();
            if (! $legacy) {
                continue;
            }

            $existing = Role::query()->where('name', $to)->where('guard_name', 'web')->first();
            if ($existing && (int) $existing->id !== (int) $legacy->id) {
                // Target name already exists as a different row — move assignments then drop legacy.
                DB::table(config('permission.table_names.model_has_roles'))
                    ->where('role_id', $legacy->id)
                    ->update(['role_id' => $existing->id]);
                $legacy->delete();

                continue;
            }

            $legacy->name = $to;
            $legacy->save();
        }
    }
}
