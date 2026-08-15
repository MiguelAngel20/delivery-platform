<?php

namespace Database\Factories;

use App\Models\BusinessBranch;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => BusinessBranch::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'sort_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }
}
