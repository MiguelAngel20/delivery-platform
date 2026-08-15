<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\DriverMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverMetric>
 */
class DriverMetricFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'driver_id' => Driver::factory(),
            'offered_orders' => 0,
            'accepted_orders' => 0,
            'rejected_orders' => 0,
            'completed_orders' => 0,
            'cancelled_orders' => 0,
            'responsible_cancellations' => 0,
            'incident_count' => 0,
            'responsible_incidents' => 0,
            'average_rating' => null,
            'total_ratings' => 0,
            'trust_score' => 50,
            'last_recalculated_at' => null,
        ];
    }
}
