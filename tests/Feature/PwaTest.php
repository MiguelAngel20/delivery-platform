<?php

use App\Models\Driver;
use App\Models\User;

test('pwa manifest is publicly accessible', function () {
    $response = $this->get('/manifest.webmanifest');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/manifest+json');
});

test('pwa service worker is publicly accessible', function () {
    $response = $this->get('/sw.js');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/javascript');
});

test('storefront home includes pwa manifest link', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('/manifest.webmanifest', false)
        ->assertSee('theme-color', false)
        ->assertSee('apple-mobile-web-app-capable', false);
});

test('driver manifest is publicly accessible', function () {
    $response = $this->get('/driver/manifest.webmanifest');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/manifest+json');
});

test('driver portal includes driver pwa manifest link', function () {
    $user = User::factory()->driver()->create();
    Driver::factory()->approved()->forUser($user)->create();

    $this->actingAs($user)
        ->get(route('driver.home'))
        ->assertOk()
        ->assertSee('/driver/manifest.webmanifest', false);
});
