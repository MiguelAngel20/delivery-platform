<?php

namespace App\Enums;

enum FinancialTransactionType: string
{
    case DriverToBusiness = 'driver_to_business';
    case CustomerToDriver = 'customer_to_driver';
    case CustomerToBusiness = 'customer_to_business';
    case CustomerToPlatform = 'customer_to_platform';
    case BusinessToDriver = 'business_to_driver';
    case PlatformToDriver = 'platform_to_driver';
    case DriverToPlatform = 'driver_to_platform';
    case PlatformToBusiness = 'platform_to_business';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::DriverToBusiness => 'Driver → Business',
            self::CustomerToDriver => 'Customer → Driver',
            self::CustomerToBusiness => 'Customer → Business',
            self::CustomerToPlatform => 'Customer → Platform',
            self::BusinessToDriver => 'Business → Driver',
            self::PlatformToDriver => 'Platform → Driver',
            self::DriverToPlatform => 'Driver → Platform',
            self::PlatformToBusiness => 'Platform → Business',
            self::Adjustment => 'Ajuste',
        };
    }

    public function isUniquePerOrder(): bool
    {
        return $this !== self::Adjustment;
    }
}
