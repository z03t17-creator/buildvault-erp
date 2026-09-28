<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Worker;
use App\Support\Roles;
use Database\Seeders\DemoHierarchySeeder;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoUsersPhase2Test extends TestCase
{
    use RefreshDatabase;

    public function test_demo_users_seed_phase2_emails_and_roles(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(DemoHierarchySeeder::class);
        $this->seed(DemoUsersSeeder::class);

        $boss = User::query()->where('email', DemoUsersSeeder::BOSS_EMAIL)->first();
        $accountant = User::query()->where('email', DemoUsersSeeder::ACCOUNTANT_EMAIL)->first();
        $stock = User::query()->where('email', DemoUsersSeeder::STOCK_EMAIL)->first();

        $this->assertNotNull($boss);
        $this->assertTrue($boss->hasRole(Roles::BOSS_CONTRACTOR));
        $this->assertTrue(Hash::check(DemoUsersSeeder::BOSS_PASSWORD, $boss->password));

        $this->assertNotNull($accountant);
        $this->assertTrue($accountant->hasRole(Roles::ACCOUNTANT));

        $this->assertNotNull($stock);
        $this->assertTrue($stock->hasRole(Roles::STOCK_MANAGER));
        $this->assertTrue(Hash::check(DemoUsersSeeder::STOCK_PASSWORD, $stock->password));

        $this->assertNull(User::query()->where('email', DemoUsersSeeder::ENGINEER_EMAIL)->first());
        $this->assertNull(User::query()->where('email', DemoUsersSeeder::WORKER_EMAIL)->first());
    }

    public function test_legacy_engineer_and_worker_are_migrated_cleanly(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(DemoHierarchySeeder::class);

        $engineer = User::factory()->create([
            'email' => DemoUsersSeeder::ENGINEER_EMAIL,
            'name' => 'Old Engineer',
        ]);
        $engineer->assignRole(Roles::BOSS_CONTRACTOR);
        $engineerId = $engineer->id;

        $workerUser = User::factory()->create([
            'email' => DemoUsersSeeder::WORKER_EMAIL,
            'name' => 'Old Worker Login',
        ]);
        $workerUser->assignRole(Roles::STOCK_MANAGER);
        Worker::query()->create([
            'user_id' => $workerUser->id,
            'name' => 'Linked Laborer',
            'role' => Worker::ROLE_LABORER,
            'project_id' => \App\Models\Project::query()->first()->id,
        ]);

        $this->seed(DemoUsersSeeder::class);

        $boss = User::query()->where('email', DemoUsersSeeder::BOSS_EMAIL)->first();
        $this->assertNotNull($boss);
        $this->assertSame($engineerId, $boss->id);
        $this->assertTrue($boss->hasRole(Roles::BOSS_CONTRACTOR));

        $this->assertNull(User::query()->where('email', DemoUsersSeeder::ENGINEER_EMAIL)->first());
        $this->assertNull(User::query()->where('email', DemoUsersSeeder::WORKER_EMAIL)->first());
        $this->assertSame(0, Worker::query()->whereNotNull('user_id')->where('name', 'Linked Laborer')->count());
        $this->assertTrue(
            Worker::query()->where('name', 'Linked Laborer')->whereNull('user_id')->exists()
        );
    }
}
