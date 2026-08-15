<?php

namespace Database\Factories;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Models\Incident;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    protected $model = Incident::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'customer_id' => null,
            'driver_id' => null,
            'business_id' => null,
            'reported_by_user_id' => null,
            'resolved_by_user_id' => null,
            'type' => IncidentType::Other,
            'severity' => IncidentSeverity::Medium,
            'status' => IncidentStatus::Open,
            'description' => 'Incidencia de prueba',
            'resolution' => null,
            'idempotency_key' => fake()->unique()->uuid(),
            'resolved_at' => null,
        ];
    }
}
