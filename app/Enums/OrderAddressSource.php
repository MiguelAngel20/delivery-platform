<?php

namespace App\Enums;

enum OrderAddressSource: string
{
    case Business = 'business';
    case SavedAddress = 'saved_address';
    case Temporary = 'temporary';
    case CurrentLocation = 'current_location';

    public function label(): string
    {
        return match ($this) {
            self::Business => 'Sucursal',
            self::SavedAddress => 'Dirección guardada',
            self::Temporary => 'Temporal',
            self::CurrentLocation => 'Ubicación actual',
        };
    }
}
