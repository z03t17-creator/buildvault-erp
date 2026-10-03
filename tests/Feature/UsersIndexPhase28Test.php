<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UsersIndexPhase28Test extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_filters_overview_and_role_status_chips(): void
    {
        $admin = $this->actingAsRole(Roles::SUPER_ADMIN);

        $boss = User::factory()->create([
            'name' => 'Boss User',
            'email' => 'boss-qa@zhako.test',
            'status' => User::STATUS_ACTIVE,
        ]);
        $boss->assignRole(Roles::BOSS_CONTRACTOR);

        $disabled = User::factory()->create([
            'name' => 'Disabled Acct',
            'email' => 'disabled-qa@zhako.test',
            'status' => User::STATUS_DISABLED,
        ]);
        $disabled->assignRole(Roles::ACCOUNTANT);

        $this->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/Index')
                ->has('users')
                ->has('filters')
                ->has('roles', 4)
                ->has('statuses', 2)
                ->has('roleCounts')
                ->has('statusCounts')
                ->where('statusCounts.all', fn ($v) => (int) $v >= 3)
                ->where('statusCounts.disabled', fn ($v) => (int) $v >= 1)
                ->where('overview.count', fn ($v) => (int) $v >= 3)
                ->where('filters.role', '')
                ->where('filters.status', '')
            );

        $this->get(route('users.index', ['status' => 'disabled']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/Index')
                ->where('filters.status', 'disabled')
                ->where('overview.disabled', fn ($v) => (int) $v >= 1)
                ->where('users.0.status', User::STATUS_DISABLED)
            );

        $this->get(route('users.index', ['role' => Roles::BOSS_CONTRACTOR]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.role', Roles::BOSS_CONTRACTOR)
                ->where('users.0.role', Roles::BOSS_CONTRACTOR)
                ->where('overview.count', fn ($v) => (int) $v >= 1)
            );

        $this->get(route('users.show', $boss))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/Show')
                ->where('userRecord.id', $boss->id)
                ->where('userRecord.role', Roles::BOSS_CONTRACTOR)
            );

        // Sanity: acting admin still present
        $this->assertTrue($admin->hasRole(Roles::SUPER_ADMIN));
    }

    public function test_empty_filter_returns_zero_overview(): void
    {
        $this->actingAsRole(Roles::SUPER_ADMIN);

        $this->get(route('users.index', [
            'role' => Roles::STOCK_MANAGER,
            'status' => 'disabled',
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/Index')
                ->has('users', 0)
                ->where('overview.count', 0)
                ->where('overview.active', 0)
                ->where('overview.disabled', 0)
                ->where('filters.role', Roles::STOCK_MANAGER)
                ->where('filters.status', 'disabled')
            );
    }
}
