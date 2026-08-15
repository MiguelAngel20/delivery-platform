<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAddress>
 */
class CustomerAddressFactory extends Factory
{
    protected $model = CustomerAddress::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'label' => 'Casa',
            'address_text' => fake()->streetAddress(),
            'reference' => fake()->optional()->sentence(3),
            'latitude' => fake()->latitude(14.5, 14.7),
            'longitude' => fake()->longitude(-90.6, -90.4),
            'google_maps_url' => null,
            'is_default' => true,
            'is_active' => true,
        ];
    }
}
