<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'light') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Prefer light brand theme; only keep dark when explicitly chosen --}}
        <script>
            (function() {
                try {
                    var stored = localStorage.getItem('appearance');
                    var appearance = stored || '{{ $appearance ?? 'light' }}';

                    if (!stored || appearance === 'system') {
                        appearance = 'light';
                        localStorage.setItem('appearance', 'light');
                        document.cookie = 'appearance=light;path=/;max-age=31536000;SameSite=Lax';
                    }

                    if (appearance === 'dark') {
                        document.documentElement.classList.add('dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                    }
                } catch (e) {
                    document.documentElement.classList.remove('dark');
                }
            })();
        </script>

        <style>
            html {
                background-color: #F8FAFC;
                color-scheme: light;
            }

            html.dark {
                background-color: #0f172a;
                color-scheme: dark;
            }
        </style>

        @php
            $pwaPortal = \App\Support\Portal::current();
            $branding = \App\Support\Portal::branding($pwaPortal);
            $appName = $branding['name'];
            $appDescription = $branding['description'];
            $manifestHref = \App\Support\Portal::manifestHref($pwaPortal);
            $shareImageUrl = url('/assets/branding/logo-horizontal.png');
            $currentUrl = url()->current();
        @endphp
        <link rel="icon" href="/assets/branding/isotipo.png" type="image/png">
        <link rel="apple-touch-icon" href="/assets/branding/isotipo.png">
        <link rel="manifest" href="{{ $manifestHref }}">
        <meta name="theme-color" content="#FF7A00">
        <meta name="description" content="{{ $appDescription }}">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="{{ $appName }}">

        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name', 'ChisDrive') }}">
        <meta property="og:title" content="{{ $appName }}">
        <meta property="og:description" content="{{ $appDescription }}">
        <meta property="og:url" content="{{ $currentUrl }}">
        <meta property="og:image" content="{{ $shareImageUrl }}">

        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $appName }}">
        <meta name="twitter:description" content="{{ $appDescription }}">
        <meta name="twitter:image" content="{{ $shareImageUrl }}">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
