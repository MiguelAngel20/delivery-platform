<?php

use App\Services\Push\FcmHttpV1PushProvider;
use App\Support\Portal;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'portals.enabled' => true,
        'portals.scheme' => 'https',
        'portals.hosts.storefront' => 'chisdrive.com',
        'portals.hosts.admin' => 'admin.chisdrive.com',
        'portals.hosts.business' => 'business.chisdrive.com',
        'portals.hosts.driver' => 'driver.chisdrive.com',
    ]);
});

test('notification links open the portal that owns the path', function (string $path, string $url) {
    expect(Portal::notificationUrl($path))->toBe($url);
})->with([
    'customer' => ['/customer/orders/CHIS-2026-000001', 'https://chisdrive.com/customer/orders/CHIS-2026-000001'],
    'driver' => ['/driver/orders', 'https://driver.chisdrive.com/driver/orders'],
    'business' => ['/business/orders/CHIS-2026-000001', 'https://business.chisdrive.com/business/orders/CHIS-2026-000001'],
    'admin' => ['/admin/orders/CHIS-2026-000001', 'https://admin.chisdrive.com/admin/orders/CHIS-2026-000001'],
]);

test('notification links reject external and protocol-relative paths', function (string $path) {
    expect(Portal::isSafeClickPath($path))->toBeFalse()
        ->and(Portal::notificationUrl($path))->toBeNull();
})->with([
    '//evil.example',
    'https://evil.example/phish',
    'http://evil.example',
]);

test('relative click paths stay internal', function () {
    expect(Portal::isSafeClickPath('/customer/orders/CHIS-2026-000001'))->toBeTrue()
        ->and(Portal::isSafeClickPath('/driver/orders'))->toBeTrue()
        ->and(Portal::isSafeClickPath('/business/orders/CHIS-2026-000001'))->toBeTrue()
        ->and(Portal::isSafeClickPath('/admin/orders/CHIS-2026-000001'))->toBeTrue();
});

test('single host mode uses the https app url', function () {
    config([
        'portals.enabled' => false,
        'app.url' => 'https://chisdrive.com',
    ]);

    expect(Portal::notificationUrl('/customer/orders/CHIS-1'))
        ->toBe('https://chisdrive.com/customer/orders/CHIS-1')
        ->and(Portal::notificationUrl('https://evil.example'))
        ->toBeNull();
});

test('fcm web push link uses the trusted portal host', function () {
    $method = new ReflectionMethod(FcmHttpV1PushProvider::class, 'httpsWebPushLink');
    $provider = new FcmHttpV1PushProvider;

    expect($method->invoke($provider, '/driver/orders'))
        ->toBe('https://driver.chisdrive.com/driver/orders')
        ->and($method->invoke($provider, 'https://evil.example/steal'))
        ->toBeNull()
        ->and($method->invoke($provider, '//evil.example'))
        ->toBeNull();
});
