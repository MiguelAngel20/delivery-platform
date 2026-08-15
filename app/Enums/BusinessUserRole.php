<?php

namespace App\Enums;

enum BusinessUserRole: string
{
    case BusinessAdmin = 'business_admin';
    case BusinessEmployee = 'business_employee';

    public function label(): string
    {
        return match ($this) {
            self::BusinessAdmin => 'Administrador',
            self::BusinessEmployee => 'Empleado',
        };
    }
}
