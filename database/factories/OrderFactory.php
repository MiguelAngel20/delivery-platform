<?php

namespace Database\Factories;

use App\Enums\BusinessOperationMode;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\BusinessBranch;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => 'RIDE-'.now()->format('Y').'-'.fake()->unique()->numerify('######'),
            'customer_id' => Customer::factory(),
            'branch_id' => BusinessBranch::factory(),
            'created_by_user_id' => null,
            'type' => OrderType::Business,
            'operation_mode' => BusinessOperationMode::Partner,
            'order_status' => OrderStatus::PendingBusiness,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::Cash,
            'subtotal_before_discount' => 100,
            'discount_total' => 0,
            'subtotal_after_discount' => 100,
            'service_fee' => 50,
            'delivery_fee' => 0,
            'total' => 150,
            'estimated_preparation_minutes' => null,
            'notes' => null,
        ];
    }
}
