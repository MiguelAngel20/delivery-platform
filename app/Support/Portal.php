<?php

namespace App\Support;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

final class Portal
{
    public const STOREFRONT = 'storefront';

    public const ADMIN = 'admin';

    public const BUSINESS = 'business';

    public const DRIVER = 'driver';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [
            self::STOREFRONT,
            self::ADMIN,
            self::BUSINESS,
            self::DRIVER,
        ];
    }

    public static function enabled(): bool
    {
        return (bool) config('portals.enabled', false);
    }

    public static function host(string $portal): string
    {
        $host = config("portals.hosts.{$portal}");

        if (! is_string($host) || $host === '') {
            throw new \InvalidArgumentException("Unknown portal host [{$portal}].");
        }

        return $host;
    }

    public static function scheme(): string
    {
        $scheme = (string) config('portals.scheme', 'https');

        return in_array($scheme, ['http', 'https'], true) ? $scheme : 'https';
    }

    public static function baseUrl(string $portal): string
    {
        return self::scheme().'://'.self::host($portal);
    }

    /**
     * Resolve portal from the current request host (when enabled) or path/login session.
     */
    public static function current(?Request $request = null): string
    {
        $request ??= request();

        if (self::enabled()) {
            $fromHost = self::fromHost($request->getHost());

            if ($fromHost !== null) {
                return $fromHost;
            }
        }

        return self::fromPathOrLogin($request);
    }

    public static function fromHost(string $host): ?string
    {
        $host = strtolower($host);

        foreach (self::keys() as $portal) {
            if (strtolower(self::host($portal)) === $host) {
                return $portal;
            }
        }

        return null;
    }

    public static function fromPathOrLogin(?Request $request = null): string
    {
        $request ??= request();

        if ($request->is('admin', 'admin/*')) {
            return self::ADMIN;
        }

        if ($request->is('business', 'business/*')) {
            return self::BUSINESS;
        }

        if ($request->is('driver', 'driver/*')) {
            return self::DRIVER;
        }

        $loginPortal = $request->session()->get('login_portal')
            ?? $request->cookie('login_portal');

        return match ($loginPortal) {
            'admin.login' => self::ADMIN,
            'business.login' => self::BUSINESS,
            'driver.login' => self::DRIVER,
            default => self::STOREFRONT,
        };
    }

    /**
     * @return list<UserRole>
     */
    public static function rolesFor(string $portal): array
    {
        return match ($portal) {
            self::ADMIN => [UserRole::SystemAdmin],
            self::BUSINESS => UserRole::businessRoles(),
            self::DRIVER => [UserRole::Driver],
            default => [UserRole::Customer],
        };
    }

    public static function allowsRole(string $portal, UserRole $role): bool
    {
        return in_array($role, self::rolesFor($portal), true);
    }

    public static function loginRouteName(string $portal): string
    {
        return match ($portal) {
            self::ADMIN => 'admin.login',
            self::BUSINESS => 'business.login',
            self::DRIVER => 'driver.login',
            default => 'login',
        };
    }

    public static function homeRouteName(string $portal): string
    {
        return match ($portal) {
            self::ADMIN => 'admin.home',
            self::BUSINESS => 'business.home',
            self::DRIVER => 'driver.home',
            default => 'home',
        };
    }

    public static function manifestHref(string $portal): string
    {
        return match ($portal) {
            self::ADMIN => '/admin/manifest.webmanifest',
            self::BUSINESS => '/business/manifest.webmanifest',
            self::DRIVER => '/driver/manifest.webmanifest',
            default => '/manifest.webmanifest',
        };
    }

    /**
     * @return array{name: string, description: string}
     */
    public static function branding(string $portal): array
    {
        return match ($portal) {
            self::ADMIN => [
                'name' => 'ChisDrive Admin',
                'description' => 'Panel de administración ChisDrive.',
            ],
            self::BUSINESS => [
                'name' => 'ChisDrive Negocio',
                'description' => 'Panel de negocios ChisDrive.',
            ],
            self::DRIVER => [
                'name' => 'ChisDrive Repartidor',
                'description' => 'Portal de repartidores ChisDrive.',
            ],
            default => [
                'name' => (string) config('app.name', 'ChisDrive'),
                'description' => 'Pide comida y más a domicilio con ChisDrive.',
            ],
        };
    }

    /**
     * Map a path prefix to its portal for cross-host redirects.
     */
    public static function fromPathPrefix(string $path): ?string
    {
        $path = '/'.ltrim($path, '/');

        if (str_starts_with($path, '/admin')) {
            return self::ADMIN;
        }

        if (str_starts_with($path, '/business')) {
            return self::BUSINESS;
        }

        if (str_starts_with($path, '/driver')) {
            return self::DRIVER;
        }

        return null;
    }

    /**
     * Absolute URL on a portal host for an absolute path (including query).
     */
    public static function absolute(string $portal, string $path = '/'): string
    {
        $path = '/'.ltrim($path, '/');

        if ($path === '/') {
            return self::baseUrl($portal).'/';
        }

        return self::baseUrl($portal).$path;
    }

    /**
     * Whether a URL points at a different host than the current request.
     */
    public static function isCrossOriginUrl(string $url, ?Request $request = null): bool
    {
        $request ??= request();
        $targetHost = parse_url($url, PHP_URL_HOST);

        if (! is_string($targetHost) || $targetHost === '') {
            return false;
        }

        return strcasecmp($targetHost, $request->getHost()) !== 0;
    }

    /**
     * Register routes optionally scoped to a portal domain.
     *
     * @param  callable(): void  $routes
     */
    public static function routes(string $portal, callable $routes): void
    {
        if (! self::enabled()) {
            $routes();

            return;
        }

        Route::domain(self::host($portal))->group($routes);
    }
}
