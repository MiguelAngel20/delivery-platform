<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Enums\UserRole;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\LogoutResponse;
use App\Models\User;
use App\Support\PhoneDialCodes;
use App\Support\PhoneNumber;
use App\Support\Portal;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(LogoutResponseContract::class, LogoutResponse::class);
    }

    public function boot(): void
    {
        $this->configureActions();
        $this->configureAuthentication();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
    }

    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $login = trim((string) $request->input(Fortify::username()));
            $portal = Portal::current($request);

            /** @var User|null $user */
            $user = $portal === Portal::STOREFRONT && ! str_contains($login, '@')
                ? $this->customerByPhone($login)
                : User::query()->where(Fortify::username(), $login)->first();

            if ($user === null || ! Hash::check((string) $request->input('password'), $user->password)) {
                return null;
            }

            if (! $user->status->canAuthenticate()) {
                throw ValidationException::withMessages([
                    Fortify::username() => __('Tu cuenta no está disponible actualmente.'),
                ]);
            }

            if (! Portal::allowsRole($portal, $user->role)) {
                throw ValidationException::withMessages([
                    Fortify::username() => __('Estas credenciales no corresponden a este portal.'),
                ]);
            }

            if ($user->role === UserRole::Driver && $user->email_verified_at === null) {
                throw ValidationException::withMessages([
                    Fortify::username() => __('Debes verificar tu correo antes de iniciar sesión. Revisa tu bandeja de entrada.'),
                ]);
            }

            if (
                $user->role === UserRole::Customer
                && $user->email_verified_at === null
                && $user->phone_verified_at === null
            ) {
                throw ValidationException::withMessages([
                    Fortify::username() => __('Confirma tu teléfono para activar la cuenta.'),
                ]);
            }

            return $user;
        });
    }

    private function customerByPhone(string $login): ?User
    {
        $candidates = [PhoneNumber::canonicalize($login)];
        $digits = preg_replace('/\D+/', '', $login) ?? '';

        if ($digits !== '' && ! str_starts_with($login, '+')) {
            $candidates[] = PhoneDialCodes::e164(PhoneDialCodes::defaultDial(), $digits);
        }

        $candidates = array_values(array_unique(array_filter($candidates)));

        if ($candidates === []) {
            return null;
        }

        return User::query()->whereIn('phone', $candidates)->first();
    }

    private function configureViews(): void
    {
        Fortify::loginView(function (Request $request) {
            if (Portal::enabled()) {
                $portal = Portal::fromHost($request->getHost()) ?? Portal::STOREFRONT;

                if ($portal !== Portal::STOREFRONT) {
                    return redirect()->route(Portal::loginRouteName($portal));
                }
            }

            $request->session()->put('login_portal', 'login');
            cookie()->queue(cookie('login_portal', 'login', 60 * 24 * 14));

            return Inertia::render('auth/login', [
                'canResetPassword' => Features::enabled(Features::resetPasswords()),
                'status' => $request->session()->get('status'),
                'title' => 'Iniciar sesión',
                'description' => 'Accede a tu cuenta',
                'submitLabel' => 'Entrar',
                'portal' => 'customer',
            ]);
        });

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
