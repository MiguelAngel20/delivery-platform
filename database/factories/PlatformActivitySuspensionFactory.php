<?php

namespace Database\Factories;

use App\Enums\PlatformSuspensionReason;
use App\Models\PlatformActivitySuspension;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformActivitySuspension>
 */
class PlatformActivitySuspensionFactory extends Factory
{
    protected $model = PlatformActivitySuspension::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'is_active' => false,
            'reason' => PlatformSuspensionReason::Maintenance,
            'updated_by' => null,
        ];
    }

    public function active(?PlatformSuspensionReason $reason = null): static
    {
        return $this->state(fn (): array => [
            'is_active' => true,
            'reason' => $reason ?? PlatformSuspensionReason::Maintenance,
        ]);
    }
}
