<?php

namespace Database\Factories;

use App\Enums\DriverApprovalStatus;
use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverPaymentModel;
use App\Enums\DriverScope;
use App\Enums\UserRole;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Driver>
 */
class DriverFactory extends Factory
{
    protected $model = Driver::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->driver(),
            'approval_status' => DriverApprovalStatus::Pending,
            'availability_status' => DriverAvailabilityStatus::Offline,
            'driver_scope' => DriverScope::Platform,
            'payment_model' => DriverPaymentModel::PlatformRate,
            'approved_by_user_id' => null,
            'approved_at' => null,
        ];
    }

    public function approved(?User $approvedBy = null): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_status' => DriverApprovalStatus::Approved,
            'availability_status' => DriverAvailabilityStatus::Available,
            'approved_by_user_id' => $approvedBy?->id,
            'approved_at' => now(),
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ])->afterMaking(function () use ($user): void {
            if ($user->role !== UserRole::Driver) {
                $user->forceFill(['role' => UserRole::Driver])->save();
            }
        });
    }
}
