<?php

use App\Support\Portal;
use Illuminate\Support\Facades\Route;

test('storefront home still resolves with portals disabled', function () {
    expect(Portal::enabled())->toBeFalse();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('/manifest.webmanifest', false);
});

test('admin and driver portal routes remain available on a single host', function () {
    $this->get(route('admin.login'))->assertOk();
    $this->get(route('driver.login'))->assertOk();
    $this->get(route('business.login'))->assertOk();
});

test('inertia converts cross origin redirects into location responses', function () {
    Route::middleware('web')->post('/__inertia_cross_origin_probe', function () {
        return redirect()->away('https://admin.chisdrive.com/admin');
    });

    $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
    ])
        ->post('https://chisdrive.com/__inertia_cross_origin_probe')
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://admin.chisdrive.com/admin');
});
