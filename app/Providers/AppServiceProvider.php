<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Super Admin bypasses all policy checks.
        Gate::before(function (User $user, string $ability) {
            return $user->hasRole(Roles::SUPER_ADMIN) ? true : null;
        });
    }
}
