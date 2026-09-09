<?php

use App\Models\Order;
use App\Services\Orders\OrderNumberGenerator;

test('generates chis prefixed order numbers for the current year', function () {
    $year = now()->format('Y');

    $number = app(OrderNumberGenerator::class)->next();

    expect($number)->toBe("CHIS-{$year}-000001");
});

test('continues sequence after existing ride numbers', function () {
    $year = now()->format('Y');

    Order::factory()->create([
        'order_number' => "RIDE-{$year}-000042",
    ]);

    $number = app(OrderNumberGenerator::class)->next();

    expect($number)->toBe("CHIS-{$year}-000043");
});

test('continues sequence after existing chis numbers', function () {
    $year = now()->format('Y');

    Order::factory()->create([
        'order_number' => "CHIS-{$year}-000010",
    ]);

    $number = app(OrderNumberGenerator::class)->next();

    expect($number)->toBe("CHIS-{$year}-000011");
});

test('continues sequence when chis number sorts before ride lexicographically', function () {
    $year = now()->format('Y');

    Order::factory()->create([
        'order_number' => "CHIS-{$year}-000999",
    ]);

    $number = app(OrderNumberGenerator::class)->next();

    expect($number)->toBe("CHIS-{$year}-001000");
});
