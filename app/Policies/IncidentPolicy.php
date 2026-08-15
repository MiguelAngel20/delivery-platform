<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\User;
use App\Support\BusinessAccess;

class IncidentPolicy
{
    public function __construct(
        private readonly BusinessAccess $businessAccess,
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

    public function view(User $user, Incident $incident): bool
    {
        if ($user->hasRole(UserRole::SystemAdmin)) {
            return true;
        }

        if ($user->hasRole(UserRole::Customer)) {
            return (int) $user->customer?->id === (int) $incident->customer_id
                || (int) $user->id === (int) $incident->reported_by_user_id;
        }

        if ($user->hasRole(UserRole::Driver)) {
            return (int) $user->driver?->id === (int) $incident->driver_id
                || (int) $user->id === (int) $incident->reported_by_user_id;
        }

        if ($user->hasRole(UserRole::BusinessAdmin, UserRole::BusinessEmployee)) {
            if ((int) $user->id === (int) $incident->reported_by_user_id) {
                return true;
            }

            $incident->loadMissing('order.branch');

            return $incident->order?->branch !== null
                && $this->businessAccess->canAccessBranch($user, $incident->order->branch);
        }

        return false;
    }

    public function resolve(User $user, Incident $incident): bool
    {
        return $user->hasRole(UserRole::SystemAdmin);
    }

    public function reviewCancellation(User $user): bool
    {
        return $user->hasRole(UserRole::SystemAdmin);
    }
}
