<?php

namespace App\Services\Geo;

use App\Enums\BranchStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Support\GeoPoint;
use Illuminate\Support\Collection;

final class BranchResolverService
{
    public function __construct(
        private readonly CoverageService $coverage,
        private readonly DistanceService $distance,
    ) {}

    public function resolveBestBranch(Business $business, float $latitude, float $longitude): ?BusinessBranch
    {
        $point = GeoPoint::make($latitude, $longitude);

        $candidates = $this->eligibleBranches($business, $point);

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates
            ->sortBy(function (BusinessBranch $branch) use ($point): int {
                return $this->distance->haversineMeters(
                    GeoPoint::make($branch->latitude, $branch->longitude),
                    $point,
                );
            })
            ->first();
    }

    /**
     * @return Collection<int, BusinessBranch>
     */
    public function eligibleBranches(Business $business, GeoPoint $point): Collection
    {
        $business->loadMissing(['branches' => fn ($query) => $query->where('status', BranchStatus::Active)]);

        return $business->branches
            ->filter(function (BusinessBranch $branch) use ($point, $business): bool {
                if ($branch->latitude === null || $branch->longitude === null) {
                    return false;
                }

                if (! $business->operation_mode->canAcceptOrders()) {
                    return true;
                }

                return $this->coverage->isPointCovered($point->latitude, $point->longitude, $branch);
            })
            ->values();
    }
}
