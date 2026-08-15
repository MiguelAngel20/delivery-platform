<?php

namespace App\Enums;

enum OrderQuoteType: string
{
    case Custom = 'custom';
    case PriceAdjustment = 'price_adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Custom => 'Pedido personalizado',
            self::PriceAdjustment => 'Ajuste de precio',
        };
    }
}
