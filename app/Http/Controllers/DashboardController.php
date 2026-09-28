<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Vault;
use App\Services\ExchangeRateService;
use App\Services\RetentionHoldService;
use App\Services\RoleHomeDashboardService;
use App\Support\Roles;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly RetentionHoldService $holds,
        private readonly ExchangeRateService $fx,
        private readonly RoleHomeDashboardService $roleHomes,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $role = $this->primaryRole($user);
        $rate = $this->fx->getUsdToIqd();

        $maturedHolds = collect();
        if ($user && ($user->can('viewRetention', Vault::class) || $user->can('manageRetention', Vault::class))) {
            $maturedHolds = $this->holds->maturedAwaitingRelease()->map(function ($hold) use ($rate) {
                $hold->setAttribute('amount_iqd', round((float) $hold->amount_usd * $rate, 0));

                return $hold;
            });
        }

        return Inertia::render('Dashboard', [
            'roleHome' => $role,
            'maturedHolds' => $maturedHolds,
            'summary' => $this->roleHomes->summaryForRole($role),
        ]);
    }

    private function primaryRole(?User $user): string
    {
        if (! $user) {
            return 'guest';
        }

        foreach (Roles::ALL as $name) {
            if ($user->hasRole($name)) {
                return $name;
            }
        }

        return 'unknown';
    }
}
