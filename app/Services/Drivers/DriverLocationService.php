<?php

namespace App\Services\Drivers;

use App\Enums\DriverAvailabilityStatus;
use App\Models\Driver;
use App\Models\DriverCurrentLocation;
use App\Support\GeoPoint;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class DriverLocationService
{
    public function update(Driver $driver, float $latitude, float $longitude, ?int $accuracyMeters = null): DriverCurrentLocation
    {
        GeoPoint::make($latitude, $longitude);

        if ($driver->availability_status === DriverAvailabilityStatus::Offline) {
            throw ValidationException::withMessages([
                'location' => 'No se actualiza ubicación mientras estás offline.',
            ]);
        }

        return DriverCurrentLocation::query()->updateOrCreate(
            ['driver_id' => $driver->id],
            [
                'latitude' => number_format($latitude, 7, '.', ''),
                'longitude' => number_format($longitude, 7, '.', ''),
                'accuracy_meters' => $accuracyMeters,
                'recorded_at' => now(),
            ],
        );
    }

    public function clear(Driver $driver): void
    {
        DriverCurrentLocation::query()->where('driver_id', $driver->id)->delete();
    }

    public function freshLocation(Driver $driver): ?DriverCurrentLocation
    {
        $driver->loadMissing('currentLocation');

        $location = $driver->currentLocation;

        if ($location === null) {
            return null;
        }

        if ($driver->availability_status === DriverAvailabilityStatus::Offline) {
            return null;
        }

        $freshness = (int) config('maps.driver_location_freshness_minutes', 15);
        $recordedAt = CarbonImmutable::parse($location->recorded_at);

        if ($recordedAt->lt(now()->subMinutes($freshness))) {
            return null;
        }

        return $location;
    }

    public function freshPoint(Driver $driver): ?GeoPoint
    {
        $location = $this->freshLocation($driver);

        if ($location === null) {
            return null;
        }

        return GeoPoint::make($location->latitude, $location->longitude);
    }
}
