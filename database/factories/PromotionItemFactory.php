<?php

namespace Database\Factories;

use App\Models\Promotion;
use App\Models\PromotionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromotionItem>
 */
class PromotionItemFactory extends Factory
{
    protected $model = PromotionItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'promotion_id' => Promotion::factory(),
            'product_id' => null,
            'name' => fake()->words(2, true),
            'description' => null,
            'quantity' => 1,
            'original_price' => null,
            'is_external_item' => true,
        ];
    }
}
