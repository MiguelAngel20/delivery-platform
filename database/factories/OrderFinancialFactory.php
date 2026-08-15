<?php

namespace Database\Factories;

use App\Enums\CollectionParty;
use App\Enums\PaymentMethod;
use App\Enums\SettlementStatus;
use App\Models\Order;
use App\Models\OrderFinancial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderFinancial>
 */
class OrderFinancialFactory extends Factory
{
    protected $model = OrderFinancial::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'products_amount' => 200,
            'discount_amount' => 0,
            'service_fee' => 50,
            'delivery_fee' => 0,
            'customer_total' => 250,
            'business_amount' => 200,
            'driver_earning' => 50,
            'platform_earning' => 0,
            'payment_method' => PaymentMethod::Cash,
            'collection_party' => CollectionParty::Driver,
            'settlement_status' => SettlementStatus::Open,
        ];
    }
}
