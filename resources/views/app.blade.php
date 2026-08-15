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

        <link rel="icon" href="/assets/branding/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/assets/branding/app-icon.svg">

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
