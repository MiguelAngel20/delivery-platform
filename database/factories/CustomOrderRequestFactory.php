<?php

namespace Database\Factories;

use App\Enums\CustomOrderRequestStatus;
use App\Models\Customer;
use App\Models\CustomOrderRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomOrderRequest>
 */
class CustomOrderRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'business_id' => null,
            'branch_id' => null,
            'establishment_name' => 'Cafetería Central',
            'description' => '2 frappés grandes de moka y 1 crepa de Nutella',
            'customer_notes' => 'Sin crema batida',
            'delivery_address_id' => null,
            'temporary_delivery_address' => [
                'address_text' => 'Calle 5 #42, Barrio Centro',
                'latitude' => '14.634900',
                'longitude' => '-90.506900',
                'reference' => null,
                'google_maps_url' => null,
            ],
            'merchant_address' => 'Av. Reforma 10, Zona 9',
            'merchant_phone' => null,
            'status' => CustomOrderRequestStatus::PendingReview,
            'assigned_admin_user_id' => null,
            'quoted_order_id' => null,
        ];
    }
}
