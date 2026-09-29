<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Block disabled accounts even if a session was opened before disable.
 */
class EnsureUserIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            // Re-read from DB — Auth may hold a stale in-memory model after disable.
            $fresh = $user->newQuery()->find($user->getKey());

            if (! $fresh || $fresh->isDisabled()) {
                Auth::logout();

                if ($request->hasSession()) {
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }

                return redirect()
                    ->route('login')
                    ->withErrors(['email' => __('This account has been disabled.')]);
            }
        }

        return $next($request);
    }
}
