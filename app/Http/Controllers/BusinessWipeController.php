<?php

namespace App\Http\Controllers;

use App\Services\BusinessDataWipeService;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Super Admin empty-books wipe from the Imports page (no Render Shell).
 */
class BusinessWipeController extends Controller
{
    public function store(
        Request $request,
        BusinessDataWipeService $wiper,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user && $user->hasRole(Roles::SUPER_ADMIN), 403);

        $request->validate([
            'confirm_wipe' => ['required', 'accepted'],
        ]);

        $result = $wiper->wipe(dryRun: false, actor: $user);

        $tables = collect($result['wiped_tables'] ?? [])
            ->filter(fn ($count) => (int) $count > 0)
            ->count();

        return back()->with(
            'success',
            __('empty_books_wipe_success', [
                'tables' => $tables,
                'users' => count($result['kept_users'] ?? []),
            ]),
        );
    }
}
