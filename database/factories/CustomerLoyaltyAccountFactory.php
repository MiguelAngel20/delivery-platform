<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerLoyaltyAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerLoyaltyAccount>
 */
class CustomerLoyaltyAccountFactory extends Factory
{
    protected $model = CustomerLoyaltyAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'qualifying_orders_count' => 0,
            'pending_reward_amount' => null,
            'pending_reward_calculator' => null,
            'reserved_order_id' => null,
            'reserved_reward_type' => null,
            'reserved_reward_amount' => null,
            'launch_consumed_at' => null,
            'launch_order_id' => null,
        ];
    }
}
