<?php

namespace App\Enums;

enum CancelledByType: string
{
    case Customer = 'customer';
    case Business = 'business';
    case Driver = 'driver';
    case Platform = 'platform';
    case SystemAdmin = 'system_admin';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Cliente',
            self::Business => 'Negocio',
            self::Driver => 'Repartidor',
            self::Platform => 'Plataforma',
            self::SystemAdmin => 'Administrador',
        };
    }
}
