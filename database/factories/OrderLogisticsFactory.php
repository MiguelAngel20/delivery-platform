<?php

namespace Database\Factories;

use App\Enums\DistanceMethod;
use App\Models\Order;
use App\Models\OrderLogistics;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderLogistics>
 */
class OrderLogisticsFactory extends Factory
{
    protected $model = OrderLogistics::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'pickup_to_delivery_distance_meters' => 2500,
            'estimated_delivery_duration_seconds' => 600,
            'distance_method' => DistanceMethod::StraightLine,
            'coverage_zone_id' => null,
            'coverage_zone_name' => null,
            'coverage_zone_type' => null,
            'coverage_radius_meters' => null,
        ];
    }
}
