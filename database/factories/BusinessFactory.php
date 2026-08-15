<?php

namespace Database\Factories;

use App\Enums\BusinessDeliveryMode;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    protected $model = Business::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'description' => fake()->optional()->sentence(),
            'business_type' => 'Restaurante',
            'operation_mode' => BusinessOperationMode::Partner,
            'delivery_mode' => BusinessDeliveryMode::PlatformDrivers,
            'status' => BusinessStatus::Active,
            'logo_path' => null,
            'phone' => fake()->numerify('+502########'),
            'email' => fake()->unique()->companyEmail(),
            'created_by_user_id' => null,
            'approved_by_user_id' => null,
            'approved_at' => now(),
            'rejection_reason' => null,
            'suspension_reason' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BusinessStatus::PendingApproval,
            'approved_at' => null,
            'approved_by_user_id' => null,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BusinessStatus::Suspended,
            'suspension_reason' => 'Suspendida por pruebas.',
        ]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Business $business): void {
            $business->limits()->firstOrCreate([], [
                'max_branches' => (int) config('business.defaults.max_branches'),
                'max_business_admins' => (int) config('business.defaults.max_business_admins'),
                'max_employees_per_branch' => (int) config('business.defaults.max_employees_per_branch'),
            ]);
        });
    }
}
