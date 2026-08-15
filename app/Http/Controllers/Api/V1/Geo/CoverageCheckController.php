<?php

namespace App\Http\Controllers\Api\V1\Geo;

use App\Http\Controllers\Controller;
use App\Models\BusinessBranch;
use App\Services\Geo\CoverageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoverageCheckController extends Controller
{
    public function __invoke(Request $request, CoverageService $coverage): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'branch_id' => ['nullable', 'integer', 'exists:business_branches,id'],
        ]);

        $branch = isset($validated['branch_id'])
            ? BusinessBranch::query()->find($validated['branch_id'])
            : null;

        $covered = $coverage->isPointCovered(
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            $branch,
        );

        $zone = $coverage->getApplicableZone(
            $branch,
            (float) $validated['latitude'],
            (float) $validated['longitude'],
        );

        return response()->json([
            'covered' => $covered,
            'message' => $covered
                ? null
                : 'Por el momento no realizamos entregas en esta ubicación.',
            'zone' => $zone === null ? null : [
                'id' => $zone->id,
                'name' => $zone->name,
                'zone_type' => $zone->zone_type->value,
                'radius_meters' => $zone->radius_meters,
            ],
        ]);
    }
}
