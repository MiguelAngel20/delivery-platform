<?php

namespace App\Enums;

enum CoverageScopeType: string
{
    case Platform = 'platform';
    case BusinessBranch = 'business_branch';

    public function label(): string
    {
        return match ($this) {
            self::Platform => 'Plataforma',
            self::BusinessBranch => 'Sucursal',
        };
    }
}
