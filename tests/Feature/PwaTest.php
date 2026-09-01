<?php

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
