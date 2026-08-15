<?php

namespace App\Enums;

enum DistanceMethod: string
{
    case StraightLine = 'straight_line';
    case RouteDistance = 'route_distance';

    public function label(): string
    {
        return match ($this) {
            self::StraightLine => 'Línea recta',
            self::RouteDistance => 'Ruta vial',
        };
    }
}
