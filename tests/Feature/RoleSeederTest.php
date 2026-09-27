<?php

namespace Tests\Feature;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_seeder_creates_exactly_four_blueprint_roles(): void
    {
        $this->seed(RoleSeeder::class);

        $this->assertSame(4, Role::query()->count());

        foreach (RoleSeeder::ROLES as $role) {
            $this->assertTrue(
                Role::where('name', $role)->where('guard_name', 'web')->exists(),
                "Missing role: {$role}",
            );
        }
    }
}
