<?php

namespace Database\Factories;

use App\Enums\FinancialPartyType;
use App\Enums\FinancialTransactionStatus;
use App\Enums\FinancialTransactionType;
use App\Enums\PaymentMethod;
use App\Models\FinancialTransaction;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialTransaction>
 */
class FinancialTransactionFactory extends Factory
{
    protected $model = FinancialTransaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'from_party_type' => FinancialPartyType::Driver,
            'from_party_id' => 1,
            'to_party_type' => FinancialPartyType::Business,
            'to_party_id' => 1,
            'transaction_type' => FinancialTransactionType::DriverToBusiness,
            'amount' => 200,
            'payment_method' => PaymentMethod::Cash,
            'status' => FinancialTransactionStatus::Completed,
            'description' => 'Factory transaction',
            'idempotency_key' => fake()->unique()->uuid(),
            'recorded_by_user_id' => null,
            'settled_at' => now(),
        ];
    }
}
