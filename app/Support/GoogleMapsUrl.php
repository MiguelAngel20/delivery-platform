<?php

namespace App\Support;

final class GoogleMapsUrl
{
    public static function fromCoordinates(float|string $latitude, float|string $longitude): string
    {
        $lat = number_format((float) $latitude, 7, '.', '');
        $lng = number_format((float) $longitude, 7, '.', '');

        return "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}";
    }

    public static function resolve(?string $storedUrl, float|string|null $latitude, float|string|null $longitude): ?string
    {
        if ($latitude !== null && $longitude !== null && $latitude !== '' && $longitude !== '') {
            return self::fromCoordinates($latitude, $longitude);
        }

        return filled($storedUrl) ? $storedUrl : null;
    }
}
