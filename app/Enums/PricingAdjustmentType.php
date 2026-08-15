<?php

namespace App\Enums;

enum PricingAdjustmentType: string
{
    case FixedDiscount = 'fixed_discount';
    case PercentageDiscount = 'percentage_discount';

    public function label(): string
    {
        return match ($this) {
            self::FixedDiscount => 'Descuento fijo',
            self::PercentageDiscount => 'Descuento porcentual',
        };
    }
}
