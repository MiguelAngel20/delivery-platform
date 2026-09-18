<?php

use App\Enums\CoverageScopeType;
use App\Enums\CoverageZoneType;
use App\Models\CoverageZone;
use App\Models\ServiceFeeDistanceSetting;
use App\Services\Geo\ServiceFeeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('service fee resolver falls back to base fee without branch coordinates', function () {
    ServiceFeeDistanceSetting::query()->delete();
    ServiceFeeDistanceSetting::factory()->create([
        'base_meters' => 1000,
        'base_fee' => 50,
        'step_meters' => 500,
        'step_fee' => 5,
    ]);

    expect(app(ServiceFeeResolver::class)->resolve(16.25, -92.13, null))->toBe('50.00');
});

test('service fee resolver uses an explicit distance snapshot', function () {
    ServiceFeeDistanceSetting::query()->delete();
    ServiceFeeDistanceSetting::factory()->create();

    $resolver = app(ServiceFeeResolver::class);

    expect($resolver->resolve(null, null, null, 1000))->toBe('50.00')
        ->and($resolver->resolve(null, null, null, 1501))->toBe('60.00');
});

test('overlapping coverage zones no longer change the service fee', function () {
    ServiceFeeDistanceSetting::query()->delete();
    ServiceFeeDistanceSetting::factory()->create();

    CoverageZone::factory()->create([
        'name' => 'Comitán',
        'scope_type' => CoverageScopeType::Platform,
        'zone_type' => CoverageZoneType::Radius,
        'center_latitude' => 16.2514,
        'center_longitude' => -92.1342,
        'radius_meters' => 5000,
        'service_fee' => 99,
        'priority' => 99,
        'is_active' => true,
    ]);

    expect(app(ServiceFeeResolver::class)->resolve(null, null, null, 800))->toBe('50.00');
});
