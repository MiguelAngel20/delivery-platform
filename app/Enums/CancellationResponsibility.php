<?php

namespace App\Enums;

enum CancellationResponsibility: string
{
    case Customer = 'customer';
    case Business = 'business';
    case Driver = 'driver';
    case Platform = 'platform';
    case None = 'none';
    case UnderReview = 'under_review';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Cliente',
            self::Business => 'Negocio',
            self::Driver => 'Repartidor',
            self::Platform => 'Plataforma',
            self::None => 'Ninguna',
            self::UnderReview => 'En revisión',
        };
    }
}
