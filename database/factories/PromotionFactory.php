<?php

namespace Database\Factories;

use App\Enums\PromotionStatus;
use App\Models\BusinessBranch;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => BusinessBranch::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'promotion_price' => fake()->randomFloat(2, 50, 200),
            'image_path' => null,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'status' => PromotionStatus::Active,
            'created_by_user_id' => null,
        ];
    }
}
