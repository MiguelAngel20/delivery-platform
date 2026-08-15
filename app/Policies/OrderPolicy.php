<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Services\Dispatch\DriverEligibilityService;
use App\Support\BusinessAccess;

class OrderPolicy
{
    public function __construct(
        private readonly BusinessAccess $businessAccess,
        private readonly DriverEligibilityService $eligibility,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->hasRole(
            UserRole::SystemAdmin,
            UserRole::BusinessAdmin,
            UserRole::BusinessEmployee,
            UserRole::Customer,
            UserRole::Driver,
        );
    }

    public function view(User $user, Order $order): bool
    {
        if ($user->hasRole(UserRole::SystemAdmin)) {
            return true;
        }

        if ($user->hasRole(UserRole::Customer)) {
            return $user->customer?->id === $order->customer_id;
        }

        if ($user->hasRole(UserRole::BusinessAdmin, UserRole::BusinessEmployee)) {
            if ($order->isPlatformManaged()) {
                return false;
            }

            $order->loadMissing('branch');

            return $order->branch !== null
                && $this->businessAccess->canAccessBranch($user, $order->branch);
        }

        if ($user->hasRole(UserRole::Driver) && $user->driver !== null) {
            if ((int) $order->assigned_driver_id === (int) $user->driver->id) {
                return true;
            }

            return $this->eligibility->isDriverEligibleForOrder($user->driver, $order);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Customer) && $user->customer !== null;
    }

    public function accept(User $user, Order $order): bool
    {
        if ($order->isPlatformManaged()) {
            return false;
        }

        return $this->canProcess($user, $order);
    }

    public function confirm(User $user, Order $order): bool
    {
        return $user->hasRole(UserRole::SystemAdmin)
            && $order->isPlatformManaged()
            && $order->order_status === OrderStatus::PendingPlatform;
    }

    public function reject(User $user, Order $order): bool
    {
        if ($order->isPlatformManaged()) {
            return $user->hasRole(UserRole::SystemAdmin);
        }

        return $this->canProcess($user, $order);
    }

    public function proposeQuote(User $user, Order $order): bool
    {
        return $user->hasRole(UserRole::SystemAdmin)
            && $order->isPlatformManaged();
    }

    public function acceptQuote(User $user, Order $order): bool
    {
        return $user->hasRole(UserRole::Customer)
            && (int) $user->customer?->id === (int) $order->customer_id;
    }

    public function markReady(User $user, Order $order): bool
    {
        return $this->canProcess($user, $order);
    }

    public function cancel(User $user, Order $order): bool
    {
        if ($user->hasRole(UserRole::SystemAdmin)) {
            return true;
        }

        if ($user->hasRole(UserRole::Customer)) {
            return $user->customer?->id === $order->customer_id;
        }

        if ($user->hasRole(UserRole::BusinessAdmin, UserRole::BusinessEmployee)) {
            return $this->canProcess($user, $order);
        }

        return false;
    }

    public function reportIncident(User $user, Order $order): bool
    {
        if ($order->order_status->isTerminal()) {
            return false;
        }

        return $this->view($user, $order);
    }

    public function viewAvailable(User $user): bool
    {
        return $user->hasRole(UserRole::Driver) && $user->driver !== null;
    }

    public function acceptDelivery(User $user, Order $order): bool
    {
        return $user->hasRole(UserRole::Driver) && $user->driver !== null;
    }

    public function rejectDelivery(User $user, Order $order): bool
    {
        return $user->hasRole(UserRole::Driver) && $user->driver !== null;
    }

    public function manageDelivery(User $user, Order $order): bool
    {
        return $user->hasRole(UserRole::Driver)
            && $user->driver !== null
            && (int) $order->assigned_driver_id === (int) $user->driver->id;
    }

    private function canProcess(User $user, Order $order): bool
    {
        if (! $user->hasRole(UserRole::BusinessAdmin, UserRole::BusinessEmployee, UserRole::SystemAdmin)) {
            return false;
        }

        if ($user->hasRole(UserRole::SystemAdmin)) {
            return true;
        }

        if ($order->isPlatformManaged()) {
            return false;
        }

        $order->loadMissing('branch');

        return $order->branch !== null
            && $this->businessAccess->canAccessBranch($user, $order->branch);
    }
}
