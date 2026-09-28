<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vault;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VaultAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_admin_with_super_admin_and_zhako_vault(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', UserSeeder::ADMIN_EMAIL)->first();

        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasRole('Super Admin'));

        $vault = Vault::query()->where('name', VaultSeeder::NAME)->first();

        $this->assertNotNull($vault);
        // VaultSeeder starts at 0; DemoInsuranceSeeder may deposit sample funds afterward.
        $this->assertGreaterThanOrEqual(0, (float) $vault->balance_usd);
        $this->assertGreaterThanOrEqual(0, (float) $vault->balance_iqd);
    }

    public function test_seeders_are_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::query()->where('email', UserSeeder::ADMIN_EMAIL)->count());
        $this->assertSame(1, Vault::query()->where('name', VaultSeeder::NAME)->count());
        $this->assertTrue(
            User::query()->where('email', UserSeeder::ADMIN_EMAIL)->first()->hasRole('Super Admin'),
        );
    }
}
