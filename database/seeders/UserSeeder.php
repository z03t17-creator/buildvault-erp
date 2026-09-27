<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Local/dev Super Admin only — not a production secret.
     */
    public const ADMIN_EMAIL = 'admin@zhako.test';

    public const ADMIN_PASSWORD = 'password';

    /**
     * Seed one Super Admin test user (idempotent by email).
     */
    public function run(): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'name' => 'Zhako Admin',
                'password' => Hash::make(self::ADMIN_PASSWORD),
                'email_verified_at' => now(),
                'locale' => 'en',
            ],
        );

        if (! $user->hasRole('Super Admin')) {
            $user->assignRole('Super Admin');
        }
    }
}
