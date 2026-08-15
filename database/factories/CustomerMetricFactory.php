<?php

namespace Database\Factories;

use App\Enums\CustomerTrustLevel;
use App\Models\Customer;
use App\Models\CustomerMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerMetric>
 */
class CustomerMetricFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'total_orders' => 0,
            'completed_orders' => 0,
            'cancelled_orders' => 0,
            'late_cancellations' => 0,
            'rejected_at_delivery' => 0,
            'payment_incidents' => 0,
            'incident_count' => 0,
            'responsible_incidents' => 0,
            'trust_score' => 50,
            'trust_level' => CustomerTrustLevel::New,
            'last_recalculated_at' => null,
        ];
    }
}
