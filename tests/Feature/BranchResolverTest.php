<?php

use App\Enums\BranchStatus;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Enums\CoverageScopeType;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\CoverageZone;
use App\Services\Geo\BranchResolverService;

test('nearest eligible branch is selected', function () {
    $business = Business::factory()->create([
        'status' => BusinessStatus::Active,
        'operation_mode' => BusinessOperationMode::Partner,
    ]);

    $near = BusinessBranch::factory()->for($business)->create([
        'name' => 'Cerca',
        'status' => BranchStatus::Active,
        'latitude' => 16.2514,
        'longitude' => -92.1342,
    ]);

    BusinessBranch::factory()->for($business)->create([
        'name' => 'Lejos',
        'status' => BranchStatus::Active,
        'latitude' => 16.30,
        'longitude' => -92.20,
    ]);

    $resolved = app(BranchResolverService::class)->resolveBestBranch($business, 16.2520, -92.1350);

    expect($resolved?->id)->toBe($near->id);
});

test('branch outside coverage is ignored', function () {
    $business = Business::factory()->create([
        'status' => BusinessStatus::Active,
        'operation_mode' => BusinessOperationMode::Partner,
    ]);

    $branch = BusinessBranch::factory()->for($business)->create([
        'status' => BranchStatus::Active,
        'latitude' => 16.2514,
        'longitude' => -92.1342,
    ]);

    CoverageZone::factory()->forBranch($branch->id)->create([
        'scope_type' => CoverageScopeType::BusinessBranch,
        'center_latitude' => 16.2514,
        'center_longitude' => -92.1342,
        'radius_meters' => 500,
        'is_active' => true,
    ]);

    $resolved = app(BranchResolverService::class)->resolveBestBranch($business, 16.30, -92.20);

    expect($resolved)->toBeNull();
});

test('inactive branch is ignored', function () {
    $business = Business::factory()->create([
        'status' => BusinessStatus::Active,
        'operation_mode' => BusinessOperationMode::Partner,
    ]);

    BusinessBranch::factory()->for($business)->create([
        'status' => BranchStatus::Inactive,
        'latitude' => 16.2514,
        'longitude' => -92.1342,
    ]);

    $resolved = app(BranchResolverService::class)->resolveBestBranch($business, 16.2520, -92.1350);

    expect($resolved)->toBeNull();
});
