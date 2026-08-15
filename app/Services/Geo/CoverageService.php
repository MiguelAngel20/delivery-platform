<?php

namespace App\Services\Geo;

use App\Enums\CoverageScopeType;
use App\Enums\CoverageZoneType;
use App\Models\BusinessBranch;
use App\Models\CoverageZone;
use App\Support\GeoPoint;
use Illuminate\Support\Collection;

final class CoverageService
{
    public function __construct(
        private readonly DistanceService $distance,
    ) {}

    public function isPointCovered(float $latitude, float $longitude, ?BusinessBranch $branch = null): bool
    {
        $point = GeoPoint::make($latitude, $longitude);

        if (! $this->passesPlatformCoverage($point)) {
            return false;
        }

        if ($branch === null) {
            return true;
        }

        return $this->passesBranchCoverage($branch, $point);
    }

    public function isOrderCovered(?BusinessBranch $branch, float $latitude, float $longitude): bool
    {
        return $this->isPointCovered($latitude, $longitude, $branch);
    }

    public function getApplicableZone(?BusinessBranch $branch, float $latitude, float $longitude): ?CoverageZone
    {
        $point = GeoPoint::make($latitude, $longitude);

        if ($branch !== null) {
            $branchZone = $this->activeBranchZones($branch)
                ->first(fn (CoverageZone $zone): bool => $this->pointInZone($zone, $point));

            if ($branchZone !== null) {
                return $branchZone;
            }
        }

        return $this->activePlatformZones()
            ->first(fn (CoverageZone $zone): bool => $this->pointInZone($zone, $point));
    }

    /**
     * Platform: if no active platform zones exist, allow (rollout-safe).
     * If any exist, the point must fall inside at least one.
     */
    private function passesPlatformCoverage(GeoPoint $point): bool
    {
        $zones = $this->activePlatformZones();

        if ($zones->isEmpty()) {
            return true;
        }

        return $zones->contains(fn (CoverageZone $zone): bool => $this->pointInZone($zone, $point));
    }

    /**
     * Branch: if the branch has no specific zones, platform coverage alone is enough.
     * If it has zones, the point must also be inside at least one branch zone.
     */
    private function passesBranchCoverage(BusinessBranch $branch, GeoPoint $point): bool
    {
        $zones = $this->activeBranchZones($branch);

        if ($zones->isEmpty()) {
            return true;
        }

        return $zones->contains(fn (CoverageZone $zone): bool => $this->pointInZone($zone, $point));
    }

    public function pointInZone(CoverageZone $zone, GeoPoint $point): bool
    {
        if (! $zone->is_active) {
            return false;
        }

        return match ($zone->zone_type) {
            CoverageZoneType::Radius => $this->pointInRadius($zone, $point),
            CoverageZoneType::Polygon => $this->pointInPolygon($zone, $point),
        };
    }

    private function pointInRadius(CoverageZone $zone, GeoPoint $point): bool
    {
        if ($zone->center_latitude === null || $zone->center_longitude === null || $zone->radius_meters === null) {
            return false;
        }

        $center = GeoPoint::make($zone->center_latitude, $zone->center_longitude);
        $meters = $this->distance->haversineMeters($center, $point);

        return $meters <= (int) $zone->radius_meters;
    }

    /**
     * Ray-casting point-in-polygon for future POLYGON zones.
     * V1 UI creates RADIUS only; this keeps the architecture ready.
     */
    private function pointInPolygon(CoverageZone $zone, GeoPoint $point): bool
    {
        $polygon = $zone->polygon;

        if (! is_array($polygon) || count($polygon) < 3) {
            return false;
        }

        $inside = false;
        $count = count($polygon);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = (float) ($polygon[$i]['lng'] ?? $polygon[$i][0] ?? 0);
            $yi = (float) ($polygon[$i]['lat'] ?? $polygon[$i][1] ?? 0);
            $xj = (float) ($polygon[$j]['lng'] ?? $polygon[$j][0] ?? 0);
            $yj = (float) ($polygon[$j]['lat'] ?? $polygon[$j][1] ?? 0);

            $intersect = (($yi > $point->latitude) !== ($yj > $point->latitude))
                && ($point->longitude < ($xj - $xi) * ($point->latitude - $yi) / (($yj - $yi) ?: 1e-12) + $xi);

            if ($intersect) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /**
     * @return Collection<int, CoverageZone>
     */
    public function activePlatformZones(): Collection
    {
        return CoverageZone::query()
            ->where('is_active', true)
            ->where('scope_type', CoverageScopeType::Platform->value)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, CoverageZone>
     */
    public function activeBranchZones(BusinessBranch $branch): Collection
    {
        return CoverageZone::query()
            ->where('is_active', true)
            ->where('scope_type', CoverageScopeType::BusinessBranch->value)
            ->where('scope_id', $branch->id)
            ->orderBy('id')
            ->get();
    }
}
