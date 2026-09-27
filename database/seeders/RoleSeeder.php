<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * BuildVault ERP roles (blueprint naming).
     *
     * @var list<string>
     */
    public const ROLES = [
        'Super Admin',
        'Accountant',
        'Site Engineer',
        'Worker',
    ];

    /**
     * Seed the four application roles.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::ROLES as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
