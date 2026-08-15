<?php

namespace App\Enums;

enum FinancialPartyType: string
{
    case Customer = 'customer';
    case Driver = 'driver';
    case Business = 'business';
    case Platform = 'platform';
    case ExternalMerchant = 'external_merchant';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Cliente',
            self::Driver => 'Repartidor',
            self::Business => 'Negocio',
            self::Platform => 'Plataforma',
            self::ExternalMerchant => 'Establecimiento externo',
        };
    }
}
