<?php

use App\Enums\CoverageScopeType;
use App\Enums\CoverageZoneType;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\CoverageZone;
use App\Services\Geo\CoverageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('point inside radius is covered', function () {
    CoverageZone::factory()->create([
        'scope_type' => CoverageScopeType::Platform,
        'zone_type' => CoverageZoneType::Radius,
        'center_latitude' => 16.2514,
        'center_longitude' => -92.1342,
        'radius_meters' => 5000,
        'is_active' => true,
    ]);

    expect(app(CoverageService::class)->isPointCovered(16.2550, -92.1300))->toBeTrue();
});

test('point outside radius is rejected', function () {
    CoverageZone::factory()->create([
        'scope_type' => CoverageScopeType::Platform,
        'center_latitude' => 16.2514,
        'center_longitude' => -92.1342,
        'radius_meters' => 1000,
        'is_active' => true,
    ]);

    expect(app(CoverageService::class)->isPointCovered(16.30, -92.20))->toBeFalse();
});

test('inactive zone is ignored', function () {
    CoverageZone::factory()->inactive()->create([
        'scope_type' => CoverageScopeType::Platform,
        'center_latitude' => 16.2514,
        'center_longitude' => -92.1342,
        'radius_meters' => 1000,
    ]);

    expect(app(CoverageService::class)->isPointCovered(16.30, -92.20))->toBeTrue();
});

test('branch coverage further restricts platform coverage', function () {
    $business = Business::factory()->create();
    $branch = BusinessBranch::factory()->for($business)->create([
        'latitude' => 16.2514,
        'longitude' => -92.1342,
    ]);

    CoverageZone::factory()->create([
        'scope_type' => CoverageScopeType::Platform,
        'center_latitude' => 16.2514,
        'center_longitude' => -92.1342,
        'radius_meters' => 20000,
        'is_active' => true,
    ]);

    CoverageZone::factory()->forBranch($branch->id)->create([
        'center_latitude' => 16.2514,
        'center_longitude' => -92.1342,
        'radius_meters' => 1000,
        'is_active' => true,
    ]);

    $coverage = app(CoverageService::class);

    expect($coverage->isOrderCovered($branch, 16.2520, -92.1335))->toBeTrue()
        ->and($coverage->isOrderCovered($branch, 16.28, -92.15))->toBeFalse();
});
