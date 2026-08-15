<?php

namespace App\Services\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\UserRole;
use App\Models\NotificationPreference;
use App\Models\User;

final class NotificationPreferenceService
{
    public function forUser(User $user): NotificationPreference
    {
        return NotificationPreference::query()->firstOrCreate(
            ['user_id' => $user->id],
            $this->defaultsForRole($user->role),
        );
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input): NotificationPreference
    {
        $preference = $this->forUser($user);
        $allowed = array_flip($this->editableKeysForRole($user->role));

        $preference->fill(array_intersect_key($input, $allowed));
        $preference->save();

        return $preference->fresh();
    }

    public function allowsPush(User $user, NotificationCategory $category, bool $critical = false): bool
    {
        $preference = $this->forUser($user);

        if (! $preference->push_enabled && ! $critical) {
            return false;
        }

        if ($critical) {
            return true;
        }

        return match ($category) {
            NotificationCategory::Order => $preference->order_updates,
            NotificationCategory::Dispatch => $preference->driver_offers,
            NotificationCategory::Business => $preference->new_orders,
            NotificationCategory::Payment => $preference->finance_updates,
            NotificationCategory::Incident => $preference->incident_updates,
            NotificationCategory::CustomOrder => $preference->custom_order_updates,
            NotificationCategory::System => $preference->system_updates,
        };
    }

    /**
     * @return array<string, bool>
     */
    public function defaultsForRole(UserRole $role): array
    {
        return match ($role) {
            UserRole::Customer => [
                'push_enabled' => true,
                'order_updates' => true,
                'new_orders' => false,
                'driver_offers' => false,
                'finance_updates' => false,
                'incident_updates' => true,
                'custom_order_updates' => true,
                'system_updates' => true,
            ],
            UserRole::Driver => [
                'push_enabled' => true,
                'order_updates' => true,
                'new_orders' => false,
                'driver_offers' => true,
                'finance_updates' => false,
                'incident_updates' => true,
                'custom_order_updates' => false,
                'system_updates' => true,
            ],
            UserRole::BusinessAdmin, UserRole::BusinessEmployee => [
                'push_enabled' => true,
                'order_updates' => true,
                'new_orders' => true,
                'driver_offers' => false,
                'finance_updates' => false,
                'incident_updates' => true,
                'custom_order_updates' => false,
                'system_updates' => true,
            ],
            UserRole::SystemAdmin => [
                'push_enabled' => true,
                'order_updates' => true,
                'new_orders' => true,
                'driver_offers' => false,
                'finance_updates' => false,
                'incident_updates' => true,
                'custom_order_updates' => true,
                'system_updates' => true,
            ],
        };
    }

    /**
     * @return list<string>
     */
    public function editableKeysForRole(UserRole $role): array
    {
        return match ($role) {
            UserRole::Customer => [
                'push_enabled',
                'order_updates',
                'custom_order_updates',
                'system_updates',
                'incident_updates',
            ],
            UserRole::Driver => [
                'push_enabled',
                'driver_offers',
                'order_updates',
                'incident_updates',
                'system_updates',
            ],
            UserRole::BusinessAdmin, UserRole::BusinessEmployee => [
                'push_enabled',
                'new_orders',
                'order_updates',
                'incident_updates',
                'system_updates',
            ],
            UserRole::SystemAdmin => [
                'push_enabled',
                'new_orders',
                'order_updates',
                'custom_order_updates',
                'incident_updates',
                'system_updates',
            ],
        };
    }
}
