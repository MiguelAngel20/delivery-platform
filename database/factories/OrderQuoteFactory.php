<?php

namespace Database\Factories;

use App\Enums\OrderQuoteStatus;
use App\Enums\OrderQuoteType;
use App\Models\CustomOrderRequest;
use App\Models\OrderQuote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderQuote>
 */
class OrderQuoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => null,
            'custom_order_request_id' => CustomOrderRequest::factory(),
            'created_by_user_id' => null,
            'type' => OrderQuoteType::Custom,
            'subtotal' => 190,
            'service_fee' => 50,
            'discount_amount' => 0,
            'total' => 240,
            'status' => OrderQuoteStatus::Pending,
            'expires_at' => null,
            'accepted_at' => null,
            'rejected_at' => null,
        ];
    }
}
