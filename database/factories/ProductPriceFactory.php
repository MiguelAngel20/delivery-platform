<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductPrice>
 */
class ProductPriceFactory extends Factory
{
    protected $model = ProductPrice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'list_price' => fake()->randomFloat(2, 20, 200),
            'valid_from' => now(),
            'valid_until' => null,
            'is_active' => true,
            'created_by_user_id' => null,
        ];
    }
}
