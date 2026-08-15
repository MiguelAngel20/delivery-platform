<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\DriverRating;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverRating>
 */
class DriverRatingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $driver = Driver::factory()->approved()->create();
        $customer = Customer::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'assigned_driver_id' => $driver->id,
            'order_status' => OrderStatus::Delivered,
            'delivered_at' => now(),
        ]);

        return [
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'customer_id' => $customer->id,
            'overall_rating' => 5,
            'speed_rating' => null,
            'service_rating' => null,
            'care_rating' => null,
            'respect_rating' => null,
            'communication_rating' => null,
            'comment' => null,
        ];
    }
}
