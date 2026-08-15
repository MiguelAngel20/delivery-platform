<?php

namespace Database\Factories;

use App\Enums\BranchStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessBranch>
 */
class BusinessBranchFactory extends Factory
{
    protected $model = BusinessBranch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => 'Sucursal '.fake()->streetName(),
            'phone' => fake()->numerify('+502########'),
            'address_text' => fake()->streetAddress(),
            'reference' => fake()->optional()->sentence(3),
            'latitude' => fake()->latitude(14.5, 14.7),
            'longitude' => fake()->longitude(-90.6, -90.4),
            'google_maps_url' => null,
            'status' => BranchStatus::Active,
        ];
    }
}
