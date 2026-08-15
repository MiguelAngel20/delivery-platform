<?php

namespace Database\Factories;

use App\Enums\PushDeviceType;
use App\Models\PushDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PushDevice>
 */
class PushDeviceFactory extends Factory
{
    protected $model = PushDevice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'fcm',
            'token' => 'fcm-'.Str::random(48),
            'device_type' => PushDeviceType::Web,
            'browser' => 'Chrome',
            'platform' => 'Windows',
            'device_name' => 'Test Device',
            'is_active' => true,
            'last_used_at' => now(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
