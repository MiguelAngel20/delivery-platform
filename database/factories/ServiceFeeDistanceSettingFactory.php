<?php

namespace Database\Factories;

use App\Models\ServiceFeeDistanceSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceFeeDistanceSetting>
 */
class ServiceFeeDistanceSettingFactory extends Factory
{
    protected $model = ServiceFeeDistanceSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'base_meters' => 1000,
            'base_fee' => '50.00',
            'step_meters' => 500,
            'step_fee' => '5.00',
        ];
    }
}
