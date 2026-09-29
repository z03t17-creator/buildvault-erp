<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $direction ?? 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0f766e">
        <meta name="application-name" content="BuildVault ERP">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="BuildVault">

        <title inertia>{{ config('app.name', 'BuildVault ERP') }}</title>

        <link rel="manifest" href="/manifest.json">
        <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">

        <!-- Fonts: Outfit display + Plus Jakarta Sans UI (Judi-clean construction) -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=outfit:500,600,700|plus-jakarta-sans:400,500,600,700&display=swap" rel="stylesheet" />

        <script>
            (function () {
                try {
                    var stored = localStorage.getItem('theme');
                    var dark = stored === 'dark' || (!stored && window.matchMedia('(prefers-color-scheme: dark)').matches);
                    if (dark) document.documentElement.classList.add('dark');
                } catch (e) {}
            })();
        </script>

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function () {
                    navigator.serviceWorker.register('/sw.js').catch(function () {});
                });
            }
        </script>
    </body>
</html>
