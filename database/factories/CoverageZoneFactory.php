<?php

namespace Database\Factories;

use App\Enums\CoverageScopeType;
use App\Enums\CoverageZoneType;
use App\Models\CoverageZone;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoverageZone>
 */
class CoverageZoneFactory extends Factory
{
    protected $model = CoverageZone::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Zona '.fake()->city(),
            'scope_type' => CoverageScopeType::Platform,
            'scope_id' => null,
            'zone_type' => CoverageZoneType::Radius,
            'center_latitude' => 16.2514000,
            'center_longitude' => -92.1342000,
            'radius_meters' => 5000,
            'polygon' => null,
            'is_active' => true,
            'created_by_user_id' => User::factory()->systemAdmin(),
        ];
    }

    public function forBranch(int $branchId): static
    {
        return $this->state(fn (): array => [
            'scope_type' => CoverageScopeType::BusinessBranch,
            'scope_id' => $branchId,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
