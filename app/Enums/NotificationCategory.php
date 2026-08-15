<?php

namespace App\Enums;

enum NotificationCategory: string
{
    case Order = 'order';
    case Dispatch = 'dispatch';
    case Payment = 'finance';
    case Incident = 'incident';
    case CustomOrder = 'custom_order';
    case System = 'system';
    case Business = 'business';

    public function label(): string
    {
        return match ($this) {
            self::Order => 'Pedidos',
            self::Dispatch => 'Dispatch',
            self::Payment => 'Finanzas',
            self::Incident => 'Incidencias',
            self::CustomOrder => 'Personalizados',
            self::System => 'Sistema',
            self::Business => 'Negocio',
        };
    }
}
