<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LoginPhase29Test extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_renders_guest_shell_props(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('canResetPassword', true)
                ->has('translations.login')
                ->has('translations.login_page_hint')
                ->has('translations.forgot_password')
                ->where('translations.login', 'Log in')
            );
    }

    public function test_login_succeeds_and_rejects_bad_credentials(): void
    {
        $user = $this->userWithRole(Roles::ACCOUNTANT);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'));

        $this->from(route('login'))
            ->post(route('login'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_forgot_password_screen_renders(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/ForgotPassword')
                ->has('translations.forgot_password_title')
                ->has('translations.forgot_password_send')
            );
    }

    public function test_disabled_user_cannot_authenticate(): void
    {
        $user = User::factory()->create([
            'status' => User::STATUS_DISABLED,
            'password' => 'password',
        ]);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $user->assignRole(Roles::ACCOUNTANT);

        $this->from(route('login'))
            ->post(route('login'), [
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
