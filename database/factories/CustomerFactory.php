<?php

namespace Database\Factories;

use App\Enums\CustomerTrustLevel;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->customer(),
            'trust_level' => CustomerTrustLevel::New,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ])->afterMaking(function (Customer $customer) use ($user): void {
            if ($user->role !== UserRole::Customer) {
                $user->forceFill(['role' => UserRole::Customer])->save();
            }
        });
    }
}
