<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Core: roles, admin, vault.
     * Demo (idempotent): hierarchy + sample attendance — safe to re-run.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            VaultSeeder::class,
            InsuranceSettingsSeeder::class,
            DemoHierarchySeeder::class,
            AttendanceSeeder::class,
            DemoInsuranceSeeder::class,
            DemoUsersSeeder::class,
            DemoExpensesSeeder::class,
            DemoAdvancesSeeder::class,
            DemoPenaltiesSeeder::class,
        ]);
    }
}
