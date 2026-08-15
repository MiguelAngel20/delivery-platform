<?php

use App\Http\Controllers\Api\V1\Geo\CoverageCheckController;
use App\Http\Controllers\Api\V1\Geo\DistanceController;
use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health');

Route::middleware('throttle:maps-geo')->group(function () {
    Route::post('/geo/coverage-check', CoverageCheckController::class)
        ->name('geo.coverage-check');
});

Route::middleware('throttle:maps-distance')->group(function () {
    Route::post('/geo/distance', DistanceController::class)
        ->name('geo.distance');
});
