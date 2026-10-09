<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AuditActions;
use App\Support\Roles;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class UserManagementPhase4Test extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_list_and_create_users(): void
    {
        $admin = $this->userWithRole(Roles::SUPER_ADMIN);

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Users/Index')->has('users'));

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'New Accountant',
                'email' => 'new.accountant@zhako.test',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => Roles::ACCOUNTANT,
                'phone' => '+9647501112222',
                'status' => User::STATUS_ACTIVE,
            ])
            ->assertRedirect();

        $created = User::query()->where('email', 'new.accountant@zhako.test')->first();
        $this->assertNotNull($created);
        $this->assertTrue($created->hasRole(Roles::ACCOUNTANT));
        $this->assertTrue(Hash::check('password', $created->password));
        $this->assertSame(User::STATUS_ACTIVE, $created->status);
        $this->assertTrue(
            Activity::query()->where('event', AuditActions::USER_CREATED)->where('subject_id', $created->id)->exists()
        );
    }

    public function test_non_admins_get_403_and_users_nav_hidden(): void
    {
        foreach ([Roles::BOSS_CONTRACTOR, Roles::ACCOUNTANT, Roles::STOCK_MANAGER] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('users.index'))->assertForbidden();
            $this->actingAs($user)->get(route('users.create'))->assertForbidden();
            $this->actingAs($user)
                ->post(route('users.store'), [
                    'name' => 'Nope',
                    'email' => "blocked-{$role}@zhako.test",
                    'password' => 'password',
                    'password_confirmation' => 'password',
                    'role' => Roles::ACCOUNTANT,
                    'status' => User::STATUS_ACTIVE,
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('auth.nav', function ($nav) {
                        return ! in_array('users', collect($nav)->values()->all(), true);
                    })
                    ->where('auth.can', function ($can) {
                        return ($can['users.viewAny'] ?? null) === false;
                    })
                );
        }
    }

    public function test_super_admin_nav_includes_users(): void
    {
        $admin = $this->userWithRole(Roles::SUPER_ADMIN);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.nav', function ($nav) {
                    return in_array('users', collect($nav)->values()->all(), true);
                })
                ->where('auth.can', function ($can) {
                    return ($can['users.viewAny'] ?? null) === true;
                })
            );
    }

    public function test_admin_can_edit_role_status_and_reset_password(): void
    {
        $admin = $this->userWithRole(Roles::SUPER_ADMIN);
        $target = $this->userWithRole(Roles::ACCOUNTANT);

        $this->actingAs($admin)
            ->put(route('users.update', $target), [
                'name' => 'Updated Name',
                'email' => 'updated@zhako.test',
                'phone' => '+9647500000001',
                'role' => Roles::BOSS_CONTRACTOR,
                'status' => User::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('users.show', $target));

        $target->refresh();
        $this->assertSame('Updated Name', $target->name);
        $this->assertSame('updated@zhako.test', $target->email);
        $this->assertTrue($target->hasRole(Roles::BOSS_CONTRACTOR));
        $this->assertTrue(
            Activity::query()->where('event', AuditActions::USER_ROLE_CHANGED)->where('subject_id', $target->id)->exists()
        );

        $this->actingAs($admin)
            ->post(route('users.reset-password', $target), [
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertRedirect(route('users.show', $target));

        $target->refresh();
        $this->assertTrue(Hash::check('new-password', $target->password));
        $this->assertTrue(
            Activity::query()->where('event', AuditActions::USER_PASSWORD_RESET)->where('subject_id', $target->id)->exists()
        );
    }

    public function test_disable_blocks_login_and_password_reset(): void
    {
        $admin = $this->userWithRole(Roles::SUPER_ADMIN);
        $target = $this->userWithRole(Roles::STOCK_MANAGER);

        $this->actingAs($admin)
            ->post(route('users.disable', $target))
            ->assertRedirect(route('users.show', $target));

        $target->refresh();
        $this->assertTrue($target->isDisabled());
        $this->assertTrue(
            Activity::query()->where('event', AuditActions::USER_DISABLED)->where('subject_id', $target->id)->exists()
        );

        $this->post('/logout');

        $this->post('/login', [
            'email' => $target->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->post('/forgot-password', ['email' => $target->email])
            ->assertSessionHasErrors('email');
    }

    public function test_enable_restores_login_and_updates_last_login(): void
    {
        $admin = $this->userWithRole(Roles::SUPER_ADMIN);
        $target = User::factory()->disabled()->create([
            'password' => 'password',
        ]);
        $this->seed(RoleSeeder::class);
        $target->assignRole(Roles::ACCOUNTANT);

        $this->actingAs($admin)
            ->post(route('users.enable', $target))
            ->assertRedirect(route('users.show', $target));

        $this->post('/logout');

        $this->post('/login', [
            'email' => $target->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $target->refresh();
        $this->assertNotNull($target->last_login_at);
        $this->assertTrue($target->isActive());
    }

    public function test_destroy_soft_disables_instead_of_hard_delete(): void
    {
        $admin = $this->userWithRole(Roles::SUPER_ADMIN);
        $target = $this->userWithRole(Roles::BOSS_CONTRACTOR);

        $this->actingAs($admin)
            ->delete(route('users.destroy', $target))
            ->assertRedirect(route('users.show', $target));

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'status' => User::STATUS_DISABLED,
        ]);
    }

    public function test_admin_cannot_disable_self(): void
    {
        $admin = $this->userWithRole(Roles::SUPER_ADMIN);

        $this->actingAs($admin)
            ->post(route('users.disable', $admin))
            ->assertSessionHasErrors('user');

        $admin->refresh();
        $this->assertTrue($admin->isActive());
    }

    public function test_seed_users_remain_manageable_with_unchanged_passwords(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(UserSeeder::class);
        $this->seed(DemoUsersSeeder::class);

        $admin = User::query()->where('email', UserSeeder::ADMIN_EMAIL)->first();
        $this->assertNotNull($admin);
        $this->assertTrue(Hash::check(UserSeeder::ADMIN_PASSWORD, $admin->password));

        $this->actingAs($admin)->get(route('users.index'))->assertOk();

        foreach ([
            DemoUsersSeeder::BOSS_EMAIL,
            DemoUsersSeeder::ACCOUNTANT_EMAIL,
            DemoUsersSeeder::STOCK_EMAIL,
        ] as $email) {
            $user = User::query()->where('email', $email)->first();
            $this->assertNotNull($user);
            $this->assertSame(User::STATUS_ACTIVE, $user->status);
            $this->actingAs($admin)->get(route('users.show', $user))->assertOk();
        }
    }
}
