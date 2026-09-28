<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Core (always): roles, admin, vault, insurance settings — fast enough for Render boot.
     * Demo*: only when SEED_DEMO is truthy, or when unset outside production.
     * Render sets SEED_DEMO=false so health checks are not blocked by demo data.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            VaultSeeder::class,
            InsuranceSettingsSeeder::class,
        ]);

        if (! $this->shouldSeedDemo()) {
            $this->command?->info('Skipping demo seeders (set SEED_DEMO=true to load sample data).');

            return;
        }

        // Ordered once — demo seeders must not re-$this->call each other.
        $this->call([
            DemoHierarchySeeder::class,
            DemoUsersSeeder::class,
            DemoInsuranceSeeder::class,
            DemoExpensesSeeder::class,
            DemoAdvancesSeeder::class,
            DemoPenaltiesSeeder::class,
            DemoProductionSeeder::class,
            DemoStockSeeder::class,
        ]);
    }

    /**
     * Demo seed is for local/dev/testing. Production (Render) skips unless SEED_DEMO=true.
     */
    protected function shouldSeedDemo(): bool
    {
        $flag = env('SEED_DEMO');

        if ($flag !== null && $flag !== '') {
            return filter_var($flag, FILTER_VALIDATE_BOOLEAN);
        }

        return ! app()->environment('production');
    }
}
