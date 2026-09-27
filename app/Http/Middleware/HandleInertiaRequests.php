<?php

namespace App\Http\Middleware;

use App\Models\RetentionHold;
use Illuminate\Http\Request;
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
        $maturedCount = 0;
        if ($request->user()) {
            $maturedCount = RetentionHold::query()
                ->where('status', RetentionHold::STATUS_MATURED)
                ->count();
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'alerts' => [
                'maturedRetentionCount' => $maturedCount,
            ],
        ];
    }
}
