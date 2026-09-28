<?php

namespace App\Http\Middleware;

use App\Models\RetentionHold;
use App\Models\Vault;
use App\Services\InsuranceSettings;
use App\Support\UserAbilities;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $can = UserAbilities::canMap($user);
        $nav = UserAbilities::navKeys($can);

        $maturedCount = 0;
        $insuranceSettings = [
            'holdback_pct' => InsuranceSettings::DEFAULT_HOLDBACK_PCT,
            'maturity_months' => InsuranceSettings::DEFAULT_MATURITY_MONTHS,
        ];

        if ($user) {
            if (Gate::forUser($user)->allows('manageRetention', Vault::class)) {
                $maturedCount = RetentionHold::query()
                    ->where('status', RetentionHold::STATUS_MATURED)
                    ->count();
            }
            $insuranceSettings = app(InsuranceSettings::class)->all();
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'can' => $can,
                'nav' => $nav,
            ],
            'alerts' => [
                'maturedRetentionCount' => $maturedCount,
            ],
            'insuranceSettings' => $insuranceSettings,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
