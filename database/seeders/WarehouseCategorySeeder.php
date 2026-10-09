<?php

namespace Database\Seeders;

use App\Models\StockCategory;
use Illuminate\Database\Seeder;

/**
 * Construction warehouse default categories for the inventory desk.
 */
class WarehouseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['name' => 'Doors', 'notes' => 'دەرگا / MDF / entrance / shaft'],
            ['name' => 'Electrical', 'notes' => 'کارەبا'],
            ['name' => 'Plumbing', 'notes' => 'ئاو و لۆڵە'],
            ['name' => 'Raw Materials', 'notes' => 'کەرەستەی خاو'],
        ];

        foreach ($defaults as $row) {
            StockCategory::query()->firstOrCreate(
                ['name' => $row['name']],
                ['notes' => $row['notes']],
            );
        }
    }
}
