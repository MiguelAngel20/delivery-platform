<?php

namespace Database\Factories;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationPreference>
 */
class NotificationPreferenceFactory extends Factory
{
    protected $model = NotificationPreference::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'push_enabled' => true,
            'order_updates' => true,
            'new_orders' => true,
            'driver_offers' => true,
            'finance_updates' => false,
            'incident_updates' => true,
            'custom_order_updates' => true,
            'system_updates' => true,
        ];
    }
}
