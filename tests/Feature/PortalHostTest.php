<?php

use App\Support\Portal;

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
