<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Vault;
use App\Models\Worker;
use App\Support\Roles;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BusinessWipePhase30Test extends TestCase
{
    use RefreshDatabase;

    public function test_business_wipe_requires_commit_flag(): void
    {
        $exit = Artisan::call('business:wipe');

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Refusing wipe', Artisan::output());
    }

    public function test_business_wipe_commit_clears_data_keeps_core_users(): void
    {
        $this->seed([
            \Database\Seeders\RoleSeeder::class,
            \Database\Seeders\UserSeeder::class,
            \Database\Seeders\VaultSeeder::class,
            DemoUsersSeeder::class,
        ]);

        Project::query()->create([
            'name' => 'Wipe Me Tower',
            'status' => Project::STATUS_ACTIVE,
        ]);
        Worker::query()->create([
            'name' => 'Wipe Me Person',
            'labor_kind' => Worker::LABOR_KIND_UNCLASSIFIED,
        ]);

        Vault::query()->update([
            'balance_usd' => 999,
            'balance_iqd' => 1_000_000,
        ]);

        $exit = Artisan::call('business:wipe', ['--commit' => true]);
        $this->assertSame(0, $exit);

        $this->assertSame(0, Project::query()->count());
        $this->assertSame(0, Worker::query()->count());

        $vault = Vault::query()->first();
        $this->assertNotNull($vault);
        $this->assertEqualsWithDelta(0.0, (float) $vault->balance_usd, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $vault->balance_iqd, 0.01);

        $this->assertDatabaseHas('users', ['email' => UserSeeder::ADMIN_EMAIL]);
        $this->assertDatabaseHas('users', ['email' => DemoUsersSeeder::BOSS_EMAIL]);
        $this->assertDatabaseHas('users', ['email' => DemoUsersSeeder::ACCOUNTANT_EMAIL]);
        $this->assertDatabaseHas('users', ['email' => DemoUsersSeeder::STOCK_EMAIL]);

        $admin = \App\Models\User::query()->where('email', UserSeeder::ADMIN_EMAIL)->first();
        $this->assertTrue($admin?->hasRole(Roles::SUPER_ADMIN));
    }

    public function test_super_admin_can_wipe_empty_books_from_web(): void
    {
        $this->seed([
            \Database\Seeders\RoleSeeder::class,
            \Database\Seeders\UserSeeder::class,
            \Database\Seeders\VaultSeeder::class,
            DemoUsersSeeder::class,
        ]);

        Project::query()->create([
            'name' => 'Web Wipe Tower',
            'status' => Project::STATUS_ACTIVE,
        ]);

        $admin = \App\Models\User::query()->where('email', UserSeeder::ADMIN_EMAIL)->first();
        $this->assertNotNull($admin);

        $this->actingAs($admin)
            ->post(route('admin.business-wipe'), ['confirm_wipe' => '1'])
            ->assertRedirect();

        $this->assertSame(0, Project::query()->count());
        $this->assertDatabaseHas('users', ['email' => UserSeeder::ADMIN_EMAIL]);
    }

    public function test_non_admin_cannot_wipe_empty_books_from_web(): void
    {
        $this->seed([
            \Database\Seeders\RoleSeeder::class,
            \Database\Seeders\UserSeeder::class,
            \Database\Seeders\VaultSeeder::class,
            DemoUsersSeeder::class,
        ]);

        $boss = \App\Models\User::query()->where('email', DemoUsersSeeder::BOSS_EMAIL)->first();
        $this->assertNotNull($boss);

        $this->actingAs($boss)
            ->post(route('admin.business-wipe'), ['confirm_wipe' => '1'])
            ->assertForbidden();
    }
}
