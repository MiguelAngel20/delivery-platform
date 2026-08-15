<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PendingBusiness = 'pending_business';
    case PendingPlatform = 'pending_platform';
    case PendingCustomerConfirmation = 'pending_customer_confirmation';
    case Accepted = 'accepted';
    case Preparing = 'preparing';
    case ReadyForPickup = 'ready_for_pickup';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';

    // Prepared for future driver flow (not transitioned in V1):
    case SearchingDriver = 'searching_driver';
    case DriverAssigned = 'driver_assigned';
    case DriverAtBusiness = 'driver_at_business';
    case PickedUp = 'picked_up';
    case OnTheWay = 'on_the_way';
    case Delivered = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::PendingBusiness => 'Nuevo',
            self::PendingPlatform => 'Pendiente RIDE',
            self::PendingCustomerConfirmation => 'Esperando cliente',
            self::Accepted => 'Aceptado',
            self::Preparing => 'Preparando',
            self::ReadyForPickup => 'Listo para recoger',
            self::Cancelled => 'Cancelado',
            self::Rejected => 'Rechazado',
            self::SearchingDriver => 'Buscando repartidor',
            self::DriverAssigned => 'Repartidor asignado',
            self::DriverAtBusiness => 'Repartidor en negocio',
            self::PickedUp => 'Recogido',
            self::OnTheWay => 'En camino',
            self::Delivered => 'Entregado',
        };
    }

    public function customerLabel(): string
    {
        return match ($this) {
            self::PendingBusiness, self::PendingPlatform => 'Pedido recibido',
            self::PendingCustomerConfirmation => 'Confirma el nuevo total',
            self::Accepted, self::Preparing, self::SearchingDriver => 'Preparando tu pedido',
            self::ReadyForPickup => 'Listo para recoger',
            self::DriverAssigned => 'Repartidor asignado',
            self::DriverAtBusiness => 'Repartidor en el establecimiento',
            self::PickedUp => 'Pedido recogido',
            self::OnTheWay => 'En camino',
            self::Delivered => 'Entregado',
            self::Rejected => 'Rechazado por el negocio',
            self::Cancelled => 'Cancelado',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Delivered,
            self::Cancelled,
            self::Rejected,
        ], true);
    }

    public function isActiveForCustomer(): bool
    {
        return ! in_array($this, [
            self::Cancelled,
            self::Rejected,
            self::Delivered,
        ], true);
    }

    public function isAwaitingMerchantConfirmation(): bool
    {
        return in_array($this, [
            self::PendingBusiness,
            self::PendingPlatform,
        ], true);
    }

    public function isEarlyCustomerCancelWindow(): bool
    {
        return in_array($this, [
            self::PendingBusiness,
            self::PendingPlatform,
            self::PendingCustomerConfirmation,
        ], true);
    }
}
