<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\DriverCurrentLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverCurrentLocation>
 */
class DriverCurrentLocationFactory extends Factory
{
    protected $model = DriverCurrentLocation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'driver_id' => Driver::factory(),
            'latitude' => 16.2514000,
            'longitude' => -92.1342000,
            'accuracy_meters' => 15,
            'recorded_at' => now(),
        ];
    }

    public function stale(): static
    {
        return $this->state(fn (): array => [
            'recorded_at' => now()->subHours(2),
        ]);
    }
}
