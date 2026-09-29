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

        $maturedHolds = collect();
        if ($user && ($user->can('viewRetention', Vault::class) || $user->can('manageRetention', Vault::class))) {
            // Native amount_iqd column — no FX invent.
            $maturedHolds = $this->holds->maturedAwaitingRelease();
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
