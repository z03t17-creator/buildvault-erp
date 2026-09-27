<?php

namespace Database\Seeders;

use App\Models\Vault;
use Illuminate\Database\Seeder;

class VaultSeeder extends Seeder
{
    public const NAME = 'Zhako';

    /**
     * Seed the central Zhako / BuildVault vault (balances start at zero).
     */
    public function run(): void
    {
        Vault::query()->firstOrCreate(
            ['name' => self::NAME],
            [
                'balance_usd' => 0,
                'balance_iqd' => 0,
            ],
        );
    }
}
