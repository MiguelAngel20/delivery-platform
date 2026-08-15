<?php

use App\Contracts\MapsClient;
use App\Enums\DistanceMethod;
use App\Services\Geo\DistanceService;
use App\Support\GeoPoint;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->maps = Mockery::mock(MapsClient::class);
    $this->distance = new DistanceService($this->maps);
});

test('haversine returns reasonable distance between known points', function () {
    $from = GeoPoint::make(16.2514, -92.1342);
    $to = GeoPoint::make(16.2514, -92.0870);

    $km = $this->distance->haversineKm($from, $to);

    expect($km)->toBeGreaterThan(4.5)
        ->and($km)->toBeLessThan(6.5);
});

test('identical coordinates return zero distance', function () {
    $point = GeoPoint::make(16.2514, -92.1342);

    expect($this->distance->haversineMeters($point, $point))->toBe(0);
});

test('invalid coordinates are rejected', function () {
    GeoPoint::make(120, 0);
})->throws(InvalidArgumentException::class);

test('measure falls back to straight line when google fails', function () {
    $from = GeoPoint::make(16.2514, -92.1342);
    $to = GeoPoint::make(16.26, -92.14);

    $this->maps->shouldReceive('routeDistance')->once()->andReturn(null);

    $result = $this->distance->measure($from, $to, DistanceMethod::RouteDistance);

    expect($result['method'])->toBe(DistanceMethod::StraightLine)
        ->and($result['distance_meters'])->toBeGreaterThan(0);
});

test('measure uses google route when available', function () {
    $from = GeoPoint::make(16.2514, -92.1342);
    $to = GeoPoint::make(16.26, -92.14);

    $this->maps->shouldReceive('routeDistance')->once()->andReturn([
        'distance_meters' => 1800,
        'duration_seconds' => 420,
    ]);

    $result = $this->distance->measure($from, $to, DistanceMethod::RouteDistance);

    expect($result['method'])->toBe(DistanceMethod::RouteDistance)
        ->and($result['distance_meters'])->toBe(1800)
        ->and($result['duration_seconds'])->toBe(420);
});
