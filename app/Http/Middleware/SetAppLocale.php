<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class SetAppLocale
{
    /** @var list<string> */
    public const SUPPORTED = ['en', 'ckb', 'ar'];

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale
            ?? $request->session()->get('locale')
            ?? config('app.locale', 'en');

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = 'en';
        }

        App::setLocale($locale);

        $direction = in_array($locale, ['ckb', 'ar'], true) ? 'rtl' : 'ltr';
        $translations = $this->loadTranslations($locale);

        Inertia::share([
            'locale' => $locale,
            'direction' => $direction,
            'translations' => $translations,
        ]);

        view()->share('direction', $direction);

        return $next($request);
    }

    /**
     * @return array<string, string>
     */
    private function loadTranslations(string $locale): array
    {
        $path = lang_path("{$locale}.json");

        if (! File::exists($path)) {
            return [];
        }

        /** @var array<string, string>|null $decoded */
        $decoded = json_decode(File::get($path), true);

        return is_array($decoded) ? $decoded : [];
    }
}
