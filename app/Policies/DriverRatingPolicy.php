<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\DriverRating;
use App\Models\Order;
use App\Models\User;

class DriverRatingPolicy
{
    public function create(User $user, Order $order): bool
    {
        return $user->hasRole(UserRole::Customer)
            && $user->customer !== null
            && (int) $user->customer->id === (int) $order->customer_id
            && $order->order_status === OrderStatus::Delivered
            && $order->assigned_driver_id !== null
            && $order->driverRating === null;
    }

    public function view(User $user, DriverRating $rating): bool
    {
        if ($user->hasRole(UserRole::SystemAdmin)) {
            return true;
        }

        if ($user->hasRole(UserRole::Customer)) {
            return (int) $user->customer?->id === (int) $rating->customer_id;
        }

        if ($user->hasRole(UserRole::Driver)) {
            return (int) $user->driver?->id === (int) $rating->driver_id;
        }

        return false;
    }

    public function update(User $user, DriverRating $rating): bool
    {
        return false;
    }

    public function delete(User $user, DriverRating $rating): bool
    {
        return false;
    }
}
