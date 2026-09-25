<?php

namespace Database\Factories;

use App\Enums\PromotionStatus;
use App\Models\BusinessBranch;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

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
            'is_recurring' => false,
            'recurrence_starts_on' => null,
            'recurrence_ends_on' => null,
            'recurring_hours' => null,
            'status' => PromotionStatus::Active,
            'created_by_user_id' => null,
        ];
    }

    /**
     * @param  list<array{day: string, is_open: bool, opens_at: string|null, closes_at: string|null}>|null  $hours
     */
    public function recurring(
        ?Carbon $startsOn = null,
        ?Carbon $endsOn = null,
        ?array $hours = null,
    ): static {
        return $this->state(fn (): array => [
            'is_recurring' => true,
            'starts_at' => null,
            'ends_at' => null,
            'recurrence_starts_on' => $startsOn ?? now()->toDateString(),
            'recurrence_ends_on' => $endsOn?->toDateString(),
            'recurring_hours' => $hours,
        ]);
    }
}
