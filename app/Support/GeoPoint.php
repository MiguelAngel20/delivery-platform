<?php

namespace App\Support;

use InvalidArgumentException;

final class GeoPoint
{
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
    ) {
        if ($latitude < -90 || $latitude > 90) {
            throw new InvalidArgumentException('La latitud debe estar entre -90 y 90.');
        }

        if ($longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException('La longitud debe estar entre -180 y 180.');
        }
    }

    public static function make(float|string $latitude, float|string $longitude): self
    {
        return new self((float) $latitude, (float) $longitude);
    }
}
