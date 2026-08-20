<?php

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\Order;
use App\Models\User;
use App\Services\Dispatch\DriverEligibilityService;
use App\Support\BusinessAccess;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('customer.{customerId}', function (User $user, int $customerId): bool {
    return $user->hasRole(UserRole::Customer)
        && (int) $user->customer?->id === $customerId;
});

Broadcast::channel('driver.{driverId}', function (User $user, int $driverId): bool {
    return $user->hasRole(UserRole::Driver)
        && (int) $user->driver?->id === $driverId;
});

Broadcast::channel('business.{businessId}', function (User $user, int $businessId): bool {
    if ($user->hasRole(UserRole::SystemAdmin)) {
        return true;
    }

    if (! $user->hasRole(UserRole::BusinessAdmin, UserRole::BusinessEmployee)) {
        return false;
    }

    $business = Business::query()->find($businessId);

    if ($business === null) {
        return false;
    }

    return app(BusinessAccess::class)->activeMembership($user, $business) !== null;
});

Broadcast::channel('branch.{branchId}', function (User $user, int $branchId): bool {
    if ($user->hasRole(UserRole::SystemAdmin)) {
        return true;
    }

    if (! $user->hasRole(UserRole::BusinessAdmin, UserRole::BusinessEmployee)) {
        return false;
    }

    $branch = BusinessBranch::query()->find($branchId);

    if ($branch === null) {
        return false;
    }

    return app(BusinessAccess::class)->canAccessBranch($user, $branch);
});

Broadcast::channel('branch.{branchId}.offers', function (User $user, int $branchId): bool {
    if (! $user->hasRole(UserRole::Driver) || $user->driver === null) {
        return false;
    }

    $branch = BusinessBranch::query()->with('business')->find($branchId);

    if ($branch?->business === null) {
        return false;
    }

    return app(DriverEligibilityService::class)
        ->matchesDeliveryMode(
            $user->driver->loadMissing(['businesses', 'branches']),
            $branch->business->delivery_mode,
            $branch->business_id,
            $branch->id,
        );
});

Broadcast::channel('order.{orderId}', function (User $user, int $orderId): bool {
    $order = Order::query()->with('branch')->find($orderId);

    if ($order === null) {
        return false;
    }

    if ($user->hasRole(UserRole::SystemAdmin)) {
        return true;
    }

    if ($user->hasRole(UserRole::Customer)) {
        return (int) $user->customer?->id === (int) $order->customer_id;
    }

    if ($user->hasRole(UserRole::Driver)) {
        return (int) $user->driver?->id === (int) $order->assigned_driver_id;
    }

    if ($user->hasRole(UserRole::BusinessAdmin, UserRole::BusinessEmployee)) {
        return $order->branch !== null
            && app(BusinessAccess::class)->canAccessBranch($user, $order->branch);
    }

    return false;
});

Broadcast::channel('admin', function (User $user): bool {
    return $user->hasRole(UserRole::SystemAdmin);
});

Broadcast::channel('user.{userId}.notifications', function (User $user, int $userId): bool {
    return (int) $user->id === $userId;
});
