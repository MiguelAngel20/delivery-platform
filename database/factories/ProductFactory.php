<?php

namespace Database\Factories;

use App\Models\BusinessBranch;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => BusinessBranch::factory(),
            'product_category_id' => null,
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'image_path' => null,
            'is_available' => true,
            'is_active' => true,
            'allow_special_instructions' => true,
        ];
    }

    public function forCategory(ProductCategory $category): static
    {
        return $this->state(fn (): array => [
            'branch_id' => $category->branch_id,
            'product_category_id' => $category->id,
        ]);
    }
}
