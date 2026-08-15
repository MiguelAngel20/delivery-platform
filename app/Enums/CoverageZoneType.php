<?php

namespace App\Enums;

enum CoverageZoneType: string
{
    case Radius = 'radius';
    case Polygon = 'polygon';

    public function label(): string
    {
        return match ($this) {
            self::Radius => 'Radio',
            self::Polygon => 'Polígono',
        };
    }
}
