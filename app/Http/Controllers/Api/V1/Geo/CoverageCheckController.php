<?php

namespace App\Http\Controllers\Api\V1\Geo;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\BusinessBranch;
use App\Models\User;
use App\Services\Geo\CoverageService;
use App\Services\Geo\ServiceFeeResolver;
use App\Services\Loyalty\CustomerLoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoverageCheckController extends Controller
{
    public function __invoke(
        Request $request,
        CoverageService $coverage,
        ServiceFeeResolver $serviceFees,
        CustomerLoyaltyService $loyalty,
    ): JsonResponse {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'branch_id' => ['nullable', 'integer', 'exists:business_branches,id'],
        ]);

        $branch = isset($validated['branch_id'])
            ? BusinessBranch::query()->find($validated['branch_id'])
            : null;

        $latitude = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];

        $covered = $coverage->isPointCovered($latitude, $longitude, $branch);
        $zone = $coverage->getApplicableZone($branch, $latitude, $longitude);
        $quote = $serviceFees->quote($latitude, $longitude, $branch);
        $serviceFee = $quote['service_fee'];
        $serviceFeeDiscount = '0.00';

        /** @var User|null $user */
        $user = $request->user();

        if ($user?->hasRole(UserRole::Customer) && $user->customer !== null) {
            $progress = $loyalty->progressFor($user->customer, $serviceFee);
            $serviceFeeDiscount = $progress['next_service_fee_discount'];
        }

        return response()->json([
            'covered' => $covered,
            'message' => $covered ? null : $coverage->unavailableMessage(),
            'service_fee' => $serviceFee,
            'service_fee_discount' => $serviceFeeDiscount,
            'distance_meters' => $quote['distance_meters'],
            'zone' => $zone === null ? null : [
                'id' => $zone->id,
                'name' => $zone->name,
                'zone_type' => $zone->zone_type->value,
                'radius_meters' => $zone->radius_meters,
            ],
        ]);
    }
}
