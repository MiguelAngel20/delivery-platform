<?php

namespace App\Enums;

enum IncidentType: string
{
    case CustomerUnreachable = 'customer_unreachable';
    case CustomerRefusedOrder = 'customer_refused_order';
    case PaymentProblem = 'payment_problem';
    case BusinessDelay = 'business_delay';
    case BusinessOrderProblem = 'business_order_problem';
    case DriverProblem = 'driver_problem';
    case OrderDamaged = 'order_damaged';
    case AddressProblem = 'address_problem';
    case SafetyIncident = 'safety_incident';
    case CancellationReview = 'cancellation_review';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CustomerUnreachable => 'Cliente no responde',
            self::CustomerRefusedOrder => 'Cliente rechazó el pedido',
            self::PaymentProblem => 'Problema de pago',
            self::BusinessDelay => 'Retraso del negocio',
            self::BusinessOrderProblem => 'Problema con el pedido',
            self::DriverProblem => 'Problema del repartidor',
            self::OrderDamaged => 'Pedido dañado',
            self::AddressProblem => 'Problema de dirección',
            self::SafetyIncident => 'Incidente de seguridad',
            self::CancellationReview => 'Cancelación en revisión',
            self::Other => 'Otro',
        };
    }

    /**
     * @return list<self>
     */
    public static function forCustomer(): array
    {
        return [
            self::BusinessDelay,
            self::BusinessOrderProblem,
            self::DriverProblem,
            self::OrderDamaged,
            self::AddressProblem,
            self::Other,
        ];
    }

    /**
     * @return list<self>
     */
    public static function forBusiness(): array
    {
        return [
            self::CustomerUnreachable,
            self::DriverProblem,
            self::BusinessOrderProblem,
            self::Other,
        ];
    }

    /**
     * @return list<self>
     */
    public static function forDriver(): array
    {
        return [
            self::CustomerUnreachable,
            self::CustomerRefusedOrder,
            self::PaymentProblem,
            self::BusinessDelay,
            self::BusinessOrderProblem,
            self::DriverProblem,
            self::OrderDamaged,
            self::AddressProblem,
            self::SafetyIncident,
            self::Other,
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(array $types): array
    {
        return array_map(
            static fn (self $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            $types,
        );
    }
}
