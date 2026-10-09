<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_shares_default_locale_and_ltr_direction(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'en')
                ->where('direction', 'ltr')
                ->where('translations.login', 'Log in')
                ->where('translations.dashboard', 'Dashboard')
                ->where('translations.locale-label', 'Language')
                ->where('translations.staff', 'Staff')
                ->where('translations.salary', 'Salary')
            );
    }

    public function test_guest_can_switch_locale_to_arabic_with_rtl(): void
    {
        $this->from(route('login'))
            ->post(route('locale.update'), ['locale' => 'ar'])
            ->assertRedirect(route('login'));

        $this->assertSame('ar', session('locale'));

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'ar')
                ->where('direction', 'rtl')
                ->where('translations.login', 'تسجيل الدخول')
                ->where('translations.locale-label', 'اللغة')
            );
    }

    public function test_guest_can_switch_locale_to_kurdish_with_rtl(): void
    {
        $this->from(route('login'))
            ->post(route('locale.update'), ['locale' => 'ckb'])
            ->assertRedirect(route('login'));

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'ckb')
                ->where('direction', 'rtl')
                ->where('translations.dashboard', 'داشبۆرد')
                ->where('translations.vault', 'قاسە')
                ->where('translations.open_zhako_vault', 'کردنەوەی قاسەی ژاکۆ')
                ->where('translations.payroll_summary', 'کورتەی مووچە')
            );
    }

    public function test_authenticated_user_locale_is_persisted_on_user(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('locale.update'), ['locale' => 'ckb'])
            ->assertRedirect(route('dashboard'));

        $this->assertSame('ckb', session('locale'));
        $this->assertSame('ckb', $user->fresh()->locale);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'ckb')
                ->where('direction', 'rtl')
            );
    }

    public function test_user_locale_takes_precedence_over_session(): void
    {
        $user = User::factory()->create(['locale' => 'ar']);

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'ar')
                ->where('direction', 'rtl')
            );
    }
}
