<?php

namespace App\Support;

use App\Enums\OrderStatus;

final class OrderActiveStatuses
{
    /**
     * Pedidos que ocupan capacidad del repartidor.
     *
     * @return list<OrderStatus>
     */
    public static function forDriver(): array
    {
        return [
            OrderStatus::DriverAssigned,
            OrderStatus::DriverAtBusiness,
            OrderStatus::ReadyForPickup,
            OrderStatus::PickedUp,
            OrderStatus::OnTheWay,
        ];
    }

    /**
     * @return list<string>
     */
    public static function forDriverValues(): array
    {
        return array_map(
            static fn (OrderStatus $status): string => $status->value,
            self::forDriver(),
        );
    }

    /**
     * Pedidos visibles como oferta (aún sin driver).
     *
     * @return list<OrderStatus>
     */
    public static function offerable(): array
    {
        return [
            OrderStatus::Preparing,
            OrderStatus::SearchingDriver,
            OrderStatus::ReadyForPickup,
        ];
    }

    /**
     * @return list<string>
     */
    public static function offerableValues(): array
    {
        return array_map(
            static fn (OrderStatus $status): string => $status->value,
            self::offerable(),
        );
    }
}
