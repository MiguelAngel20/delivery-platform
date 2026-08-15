<?php

namespace App\Http\Controllers\Api\V1\Geo;

use App\Http\Controllers\Controller;
use App\Services\Geo\DistanceService;
use App\Support\GeoPoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DistanceController extends Controller
{
    public function __invoke(Request $request, DistanceService $distance): JsonResponse
    {
        $validated = $request->validate([
            'from_latitude' => ['required', 'numeric', 'between:-90,90'],
            'from_longitude' => ['required', 'numeric', 'between:-180,180'],
            'to_latitude' => ['required', 'numeric', 'between:-90,90'],
            'to_longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $from = GeoPoint::make($validated['from_latitude'], $validated['from_longitude']);
        $to = GeoPoint::make($validated['to_latitude'], $validated['to_longitude']);
        $result = $distance->measure($from, $to);

        return response()->json([
            'distance_meters' => $result['distance_meters'],
            'duration_seconds' => $result['duration_seconds'],
            'method' => $result['method']->value,
            'distance_km' => round($result['distance_meters'] / 1000, 2),
        ]);
    }
}
