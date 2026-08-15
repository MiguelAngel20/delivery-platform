<?php

namespace App\Enums;

enum UserRole: string
{
    case SystemAdmin = 'system_admin';
    case BusinessAdmin = 'business_admin';
    case BusinessEmployee = 'business_employee';
    case Driver = 'driver';
    case Customer = 'customer';

    /**
     * @return list<self>
     */
    public static function businessRoles(): array
    {
        return [
            self::BusinessAdmin,
            self::BusinessEmployee,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::SystemAdmin => 'Administrador del sistema',
            self::BusinessAdmin => 'Administrador de negocio',
            self::BusinessEmployee => 'Empleado de negocio',
            self::Driver => 'Repartidor',
            self::Customer => 'Cliente',
        };
    }

    public function homeRouteName(): string
    {
        return match ($this) {
            self::SystemAdmin => 'admin.home',
            self::BusinessAdmin, self::BusinessEmployee => 'business.home',
            self::Driver => 'driver.home',
            self::Customer => 'customer.home',
        };
    }

    public function loginRouteName(): string
    {
        return match ($this) {
            self::SystemAdmin => 'admin.login',
            self::BusinessAdmin, self::BusinessEmployee => 'business.login',
            self::Driver => 'driver.login',
            self::Customer => 'login',
        };
    }
}
