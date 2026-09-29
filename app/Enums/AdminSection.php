<?php

namespace App\Enums;

enum AdminSection: string
{
    case Businesses = 'businesses';
    case BusinessTypes = 'business_types';
    case Coverage = 'coverage';
    case Drivers = 'drivers';
    case Customers = 'customers';
    case Orders = 'orders';
    case CustomOrders = 'custom_orders';
    case Incidents = 'incidents';
    case Finance = 'finance';
    case Promotions = 'promotions';
    case Reports = 'reports';
    case Settings = 'settings';

    public function label(): string
    {
        return match ($this) {
            self::Businesses => 'Empresas',
            self::BusinessTypes => 'Tipos / giros',
            self::Coverage => 'Cobertura',
            self::Drivers => 'Repartidores',
            self::Customers => 'Clientes',
            self::Orders => 'Pedidos',
            self::CustomOrders => 'Personalizados',
            self::Incidents => 'Incidencias',
            self::Finance => 'Finanzas',
            self::Promotions => 'Promociones',
            self::Reports => 'Reportes',
            self::Settings => 'Configuración',
        };
    }

    public function hint(): ?string
    {
        return match ($this) {
            self::Settings => 'Editar permite suspender la actividad de toda la plataforma. Las cuentas de administrador solo las gestiona el dueño.',
            self::Finance => 'Solo consulta. No tiene alta ni eliminación.',
            self::Promotions, self::Reports => 'Solo consulta.',
            default => null,
        };
    }

    /**
     * @return list<self>
     */
    public static function assignable(): array
    {
        return self::cases();
    }
}
