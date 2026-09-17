<?php

use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaCrossOriginRedirects;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use App\Support\Portal;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state', 'login_portal']);

        $middleware->web(append: [
            HandleInertiaCrossOriginRedirects::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            EnsurePasswordIsChanged::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request): string {
            return route(Portal::loginRouteName(Portal::current($request)));
        });

        $middleware->redirectUsersTo(function (Request $request): string {
            /** @var User|null $user */
            $user = Auth::user();

            if ($user === null) {
                return route('home');
            }

            $portal = Portal::current($request);

            // Session cookies must stay host-scoped (SESSION_DOMAIN=null). If a
            // wrong-role session still reaches this portal, drop it and show login
            // instead of XHR-redirecting to another subdomain (CORS).
            if (! Portal::allowsRole($portal, $user->role)) {
                Auth::logout();

                if ($request->hasSession()) {
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }

                return route(Portal::loginRouteName($portal));
            }

            if ($user->mustChangePassword()) {
                return route('password.force.edit');
            }

            return $user->homeRoute();
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
