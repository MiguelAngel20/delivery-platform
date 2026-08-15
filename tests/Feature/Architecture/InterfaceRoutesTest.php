<?php

use App\Models\User;

test('authenticated users can visit interface homes', function (string $factoryState, string $routeName, int $status = 200) {
    $user = User::factory()->{$factoryState}()->create();

    $response = $this->actingAs($user)->get(route($routeName));

    if ($status === 302) {
        $response->assertRedirect();

        return;
    }

    $response->assertOk();
})->with([
    'customer' => ['customer', 'customer.home', 302],
    'business' => ['businessAdmin', 'business.home', 200],
    'driver' => ['driver', 'driver.home', 200],
    'admin' => ['systemAdmin', 'admin.home', 200],
]);
