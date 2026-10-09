<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Full demo dataset for local / SEED_DEMO=true environments.
 *
 * Order matters — do not re-$this->call nested dependencies inside child seeders.
 * IQD-first sample money; no attendance rows (product has no attendance UI).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DemoHierarchySeeder::class,
            DemoUsersSeeder::class,
            DemoInsuranceSeeder::class,
            DemoExpensesSeeder::class,
            DemoAdvancesSeeder::class,
            DemoPenaltiesSeeder::class,
            DemoProductionSeeder::class,
            DemoSpatialSeeder::class,
            DemoStockSeeder::class,
            DemoSettlementsSeeder::class,
        ]);
    }
}
