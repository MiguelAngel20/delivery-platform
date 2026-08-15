<?php

namespace App\Enums;

enum OrderType: string
{
    case Business = 'business';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Business => 'Negocio',
            self::Custom => 'Personalizado',
        };
    }
}
