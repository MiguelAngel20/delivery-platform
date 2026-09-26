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
            self::PendingPlatform => 'Pendiente ChisDrive',
            self::PendingCustomerConfirmation => 'Esperando cliente',
            self::Accepted => 'En confirmación',
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

    public function driverLabel(): string
    {
        return match ($this) {
            self::DriverAssigned => 'Ve al establecimiento',
            self::ReadyForPickup => 'Listo para recoger',
            self::DriverAtBusiness => 'En el establecimiento',
            self::PickedUp => 'En camino al cliente',
            self::OnTheWay => 'Afuera del domicilio',
            self::Delivered => 'Entregado',
            default => $this->label(),
        };
    }

    public function customerLabel(): string
    {
        return match ($this) {
            self::PendingBusiness, self::PendingPlatform => 'Esperando confirmación',
            self::PendingCustomerConfirmation => 'Confirma el nuevo total',
            self::Accepted => 'Tu pedido está en confirmación',
            self::Preparing,
            self::SearchingDriver,
            self::ReadyForPickup,
            self::DriverAssigned,
            self::DriverAtBusiness => 'Tu pedido se está preparando',
            self::PickedUp => 'Tu pedido va en camino',
            self::OnTheWay => 'Tu pedido ya está afuera de tu domicilio',
            self::Delivered => 'Entregado',
            self::Rejected => 'Pedido rechazado',
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

    public function isAwaitingPreparationAcceptance(): bool
    {
        return $this === self::Accepted;
    }

    public function isEarlyCustomerCancelWindow(): bool
    {
        return in_array($this, [
            self::PendingBusiness,
            self::PendingPlatform,
            self::PendingCustomerConfirmation,
        ], true);
    }

    /**
     * Lower values appear first in the business portal order list.
     */
    public function businessListSortPriority(): int
    {
        return match ($this) {
            self::PendingBusiness => 0,
            self::Preparing,
            self::ReadyForPickup,
            self::Accepted,
            self::SearchingDriver,
            self::DriverAssigned,
            self::DriverAtBusiness,
            self::PickedUp,
            self::OnTheWay => 1,
            self::PendingPlatform,
            self::PendingCustomerConfirmation => 2,
            self::Rejected => 3,
            self::Delivered => 4,
            self::Cancelled => 5,
        };
    }

    /**
     * Lower values appear first in the admin orders list.
     * Pending → in kitchen / in progress → delivered → cancelled.
     */
    public function adminListSortPriority(): int
    {
        return match ($this) {
            self::PendingBusiness,
            self::PendingPlatform,
            self::PendingCustomerConfirmation => 0,
            self::Accepted,
            self::Preparing,
            self::ReadyForPickup,
            self::SearchingDriver,
            self::DriverAssigned,
            self::DriverAtBusiness,
            self::PickedUp,
            self::OnTheWay => 1,
            self::Delivered => 2,
            self::Rejected,
            self::Cancelled => 3,
        };
    }
}
