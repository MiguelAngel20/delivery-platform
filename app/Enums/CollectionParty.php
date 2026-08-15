<?php

namespace App\Enums;

enum CollectionParty: string
{
    case Driver = 'driver';
    case Business = 'business';
    case Platform = 'platform';

    public function label(): string
    {
        return match ($this) {
            self::Driver => 'Repartidor',
            self::Business => 'Negocio',
            self::Platform => 'Plataforma',
        };
    }
}
