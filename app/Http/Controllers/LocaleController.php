<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetAppLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Persist the selected locale in the session (and on the user when authenticated).
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:'.implode(',', SetAppLocale::SUPPORTED)],
        ]);

        $request->session()->put('locale', $validated['locale']);

        if ($request->user()) {
            $request->user()->forceFill([
                'locale' => $validated['locale'],
            ])->save();
        }

        return back();
    }
}
