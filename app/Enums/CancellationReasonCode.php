<?php

namespace App\Enums;

enum CancellationReasonCode: string
{
    case CustomerChangedMind = 'customer_changed_mind';
    case CustomerWrongAddress = 'customer_wrong_address';
    case CustomerDuplicateOrder = 'customer_duplicate_order';
    case CustomerOther = 'customer_other';

    case BusinessOutOfStock = 'business_out_of_stock';
    case BusinessTooBusy = 'business_too_busy';
    case BusinessClosed = 'business_closed';
    case BusinessCannotPrepare = 'business_cannot_prepare';
    case BusinessOther = 'business_other';

    case DriverVehicleProblem = 'driver_vehicle_problem';
    case DriverEmergency = 'driver_emergency';
    case DriverCannotComplete = 'driver_cannot_complete';
    case DriverOther = 'driver_other';

    case CustomerUnreachable = 'customer_unreachable';
    case CustomerRefusedDelivery = 'customer_refused_delivery';
    case BusinessDelay = 'business_delay';
    case OrderDamaged = 'order_damaged';
    case AddressNotFound = 'address_not_found';
    case SafetyIssue = 'safety_issue';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CustomerChangedMind => 'Cambié de opinión',
            self::CustomerWrongAddress => 'Dirección incorrecta',
            self::CustomerDuplicateOrder => 'Pedido duplicado',
            self::CustomerOther => 'Otro motivo',
            self::BusinessOutOfStock => 'Sin existencias',
            self::BusinessTooBusy => 'Demasiada demanda',
            self::BusinessClosed => 'Establecimiento cerrado',
            self::BusinessCannotPrepare => 'No se puede preparar',
            self::BusinessOther => 'Otro motivo',
            self::DriverVehicleProblem => 'Problema con el vehículo',
            self::DriverEmergency => 'Emergencia',
            self::DriverCannotComplete => 'No puedo completar la entrega',
            self::DriverOther => 'Otro motivo',
            self::CustomerUnreachable => 'Cliente no responde',
            self::CustomerRefusedDelivery => 'Cliente rechazó la entrega',
            self::BusinessDelay => 'Retraso del negocio',
            self::OrderDamaged => 'Pedido dañado',
            self::AddressNotFound => 'No se encontró la dirección',
            self::SafetyIssue => 'Incidente de seguridad',
            self::Other => 'Otro',
        };
    }

    /**
     * @return list<self>
     */
    public static function forCustomer(): array
    {
        return [
            self::CustomerChangedMind,
            self::CustomerWrongAddress,
            self::CustomerDuplicateOrder,
            self::CustomerOther,
        ];
    }

    /**
     * @return list<self>
     */
    public static function forBusiness(): array
    {
        return [
            self::BusinessOutOfStock,
            self::BusinessTooBusy,
            self::BusinessClosed,
            self::BusinessCannotPrepare,
            self::BusinessOther,
        ];
    }

    /**
     * @return list<self>
     */
    public static function forDriver(): array
    {
        return [
            self::DriverVehicleProblem,
            self::DriverEmergency,
            self::DriverCannotComplete,
            self::CustomerUnreachable,
            self::AddressNotFound,
            self::SafetyIssue,
            self::DriverOther,
        ];
    }

    /**
     * @return list<self>
     */
    public static function forAdmin(): array
    {
        return self::cases();
    }

    public function impliesBusinessResponsibility(): bool
    {
        return in_array($this, [
            self::BusinessOutOfStock,
            self::BusinessTooBusy,
            self::BusinessClosed,
            self::BusinessCannotPrepare,
        ], true);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(array $codes): array
    {
        return array_map(
            static fn (self $code): array => [
                'value' => $code->value,
                'label' => $code->label(),
            ],
            $codes,
        );
    }
}
