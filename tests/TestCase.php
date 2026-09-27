<?php

namespace Tests;

use App\Models\User;
use App\Support\Roles;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create (and optionally act as) a user with the given Spatie role.
     */
    protected function userWithRole(string $role = Roles::SUPER_ADMIN): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    protected function actingAsRole(string $role = Roles::SUPER_ADMIN): User
    {
        $user = $this->userWithRole($role);
        $this->actingAs($user);

        return $user;
    }
}
