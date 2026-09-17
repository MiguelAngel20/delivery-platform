<?php

use App\Enums\UserRole;
use App\Support\Portal;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class);

test('portal resolves branding and login routes', function () {
    expect(Portal::loginRouteName(Portal::ADMIN))->toBe('admin.login')
        ->and(Portal::loginRouteName(Portal::DRIVER))->toBe('driver.login')
        ->and(Portal::manifestHref(Portal::BUSINESS))->toBe('/business/manifest.webmanifest')
        ->and(Portal::branding(Portal::ADMIN)['name'])->toBe('ChisDrive Admin')
        ->and(Portal::allowsRole(Portal::ADMIN, UserRole::SystemAdmin))->toBeTrue()
        ->and(Portal::allowsRole(Portal::ADMIN, UserRole::Customer))->toBeFalse()
        ->and(Portal::allowsRole(Portal::BUSINESS, UserRole::BusinessEmployee))->toBeTrue();
});

test('portal resolves from path and login session', function () {
    $adminRequest = Request::create('/admin/orders', 'GET');
    $adminRequest->setLaravelSession(app('session.store'));

    expect(Portal::fromPathOrLogin($adminRequest))->toBe(Portal::ADMIN);

    $loginRequest = Request::create('/login', 'POST');
    $loginRequest->setLaravelSession(app('session.store'));
    $loginRequest->session()->put('login_portal', 'driver.login');

    expect(Portal::fromPathOrLogin($loginRequest))->toBe(Portal::DRIVER);
});

test('portal absolute urls use configured host and scheme', function () {
    config([
        'portals.enabled' => true,
        'portals.scheme' => 'https',
        'portals.hosts.admin' => 'admin.chisdrive.com',
    ]);

    expect(Portal::absolute(Portal::ADMIN, '/admin/login'))
        ->toBe('https://admin.chisdrive.com/admin/login');
});
