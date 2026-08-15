<?php

namespace App\Enums;

enum UpgradeRequestType: string
{
    case AdditionalBranch = 'additional_branch';
    case AdditionalEmployees = 'additional_employees';

    public function label(): string
    {
        return match ($this) {
            self::AdditionalBranch => 'Sucursal adicional',
            self::AdditionalEmployees => 'Empleados adicionales',
        };
    }
}
