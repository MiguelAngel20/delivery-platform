<?php

namespace App\Enums;

enum BusinessDeliveryMode: string
{
    case OwnDrivers = 'own_drivers';
    case PlatformDrivers = 'platform_drivers';
    case Hybrid = 'hybrid';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::OwnDrivers => 'Repartidores propios',
            self::PlatformDrivers => 'Repartidores RIDE',
            self::Hybrid => 'Híbrido',
            self::None => 'Sin reparto',
        };
    }

    public function usesOwnDrivers(): bool
    {
        return $this === self::OwnDrivers || $this === self::Hybrid;
    }
}
