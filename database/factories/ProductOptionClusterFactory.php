<?php

namespace Database\Factories;

use App\Models\ProductOptionCluster;
use App\Models\ProductOptionGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductOptionCluster>
 */
class ProductOptionClusterFactory extends Factory
{
    protected $model = ProductOptionCluster::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'option_group_id' => ProductOptionGroup::factory(),
            'name' => fake()->words(2, true),
            'sort_order' => 0,
        ];
    }
}
