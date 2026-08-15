<?php

namespace Database\Factories;

use App\Enums\OrderAddressSource;
use App\Enums\OrderAddressType;
use App\Models\Order;
use App\Models\OrderAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderAddress>
 */
class OrderAddressFactory extends Factory
{
    protected $model = OrderAddress::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'type' => OrderAddressType::Delivery,
            'source' => OrderAddressSource::Temporary,
            'address_text' => fake()->streetAddress(),
            'reference' => null,
            'latitude' => '14.6349000',
            'longitude' => '-90.5069000',
            'google_maps_url' => null,
            'created_at' => now(),
        ];
    }
}
