<?php

namespace App\Enums;

enum DriverScope: string
{
    case BusinessOnly = 'business_only';
    case Platform = 'platform';

    public function label(): string
    {
        return match ($this) {
            self::BusinessOnly => 'Solo empresas',
            self::Platform => 'Plataforma',
        };
    }
}
