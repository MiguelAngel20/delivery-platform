<?php

namespace Database\Factories;

use App\Enums\ProductOptionGroupType;
use App\Models\Product;
use App\Models\ProductOptionGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductOptionGroup>
 */
class ProductOptionGroupFactory extends Factory
{
    protected $model = ProductOptionGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => fake()->words(2, true),
            'type' => ProductOptionGroupType::Choice,
            'is_required' => false,
            'min_selection' => 0,
            'max_selection' => 1,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function removable(): static
    {
        return $this->state(fn (): array => [
            'type' => ProductOptionGroupType::Removable,
            'is_required' => false,
            'min_selection' => 0,
            'max_selection' => 10,
        ]);
    }

    public function addon(): static
    {
        return $this->state(fn (): array => [
            'type' => ProductOptionGroupType::Addon,
            'is_required' => false,
            'min_selection' => 0,
            'max_selection' => 5,
        ]);
    }

    public function choice(): static
    {
        return $this->state(fn (): array => [
            'type' => ProductOptionGroupType::Choice,
            'is_required' => true,
            'min_selection' => 1,
            'max_selection' => 1,
        ]);
    }
}
