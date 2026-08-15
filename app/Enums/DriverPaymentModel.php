<?php

namespace App\Enums;

enum DriverPaymentModel: string
{
    case PlatformRate = 'platform_rate';
    case BusinessRate = 'business_rate';
    case External = 'external';

    public function label(): string
    {
        return match ($this) {
            self::PlatformRate => 'Tarifa plataforma',
            self::BusinessRate => 'Tarifa empresa',
            self::External => 'Externo',
        };
    }
}
