<?php

namespace Database\Factories;

use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductOption>
 */
class ProductOptionFactory extends Factory
{
    protected $model = ProductOption::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'option_group_id' => ProductOptionGroup::factory(),
            'name' => fake()->word(),
            'description' => null,
            'price_modifier' => 0,
            'is_default' => false,
            'is_available' => true,
            'sort_order' => 0,
        ];
    }
}
