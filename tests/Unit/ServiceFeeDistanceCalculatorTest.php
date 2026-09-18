<?php

use App\Models\ServiceFeeDistanceSetting;
use App\Services\Geo\ServiceFeeDistanceCalculator;

test('distance fee uses base price within the included meters', function () {
    $calculator = app(ServiceFeeDistanceCalculator::class);
    $settings = [
        'base_meters' => 1000,
        'base_fee' => 50,
        'step_meters' => 500,
        'step_fee' => 5,
    ];

    expect($calculator->calculate(0, $settings))->toBe('50.00')
        ->and($calculator->calculate(1000, $settings))->toBe('50.00');
});

test('distance fee adds a step for each extra half kilometer', function () {
    $calculator = app(ServiceFeeDistanceCalculator::class);
    $settings = [
        'base_meters' => 1000,
        'base_fee' => 50,
        'step_meters' => 500,
        'step_fee' => 5,
    ];

    expect($calculator->calculate(1001, $settings))->toBe('55.00')
        ->and($calculator->calculate(1500, $settings))->toBe('55.00')
        ->and($calculator->calculate(1501, $settings))->toBe('60.00')
        ->and($calculator->calculate(2000, $settings))->toBe('60.00')
        ->and($calculator->calculate(2001, $settings))->toBe('65.00');
});

test('distance fee respects custom admin settings', function () {
    $calculator = app(ServiceFeeDistanceCalculator::class);
    $settings = ServiceFeeDistanceSetting::factory()->make([
        'base_meters' => 2000,
        'base_fee' => '40.00',
        'step_meters' => 1000,
        'step_fee' => '10.00',
    ]);

    expect($calculator->calculate(2000, $settings))->toBe('40.00')
        ->and($calculator->calculate(2001, $settings))->toBe('50.00')
        ->and($calculator->calculate(3500, $settings))->toBe('60.00');
});
