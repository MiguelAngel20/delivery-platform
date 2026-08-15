<?php

namespace Database\Factories;

use App\Models\OrderQuote;
use App\Models\OrderQuoteItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderQuoteItem>
 */
class OrderQuoteItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quote_id' => OrderQuote::factory(),
            'description' => 'Frappé de moka',
            'quantity' => 1,
            'unit_price' => 60,
            'subtotal' => 60,
            'acquisition_cost' => null,
            'notes' => null,
        ];
    }
}
