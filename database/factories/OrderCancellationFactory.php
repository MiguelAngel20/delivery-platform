<?php

namespace Database\Factories;

use App\Enums\CancellationReasonCode;
use App\Enums\CancellationResponsibility;
use App\Enums\CancellationReviewStatus;
use App\Enums\CancelledByType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderCancellation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderCancellation>
 */
class OrderCancellationFactory extends Factory
{
    protected $model = OrderCancellation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'cancelled_by_user_id' => null,
            'cancelled_by_type' => CancelledByType::Customer,
            'reason_code' => CancellationReasonCode::CustomerChangedMind,
            'reason' => null,
            'previous_order_status' => OrderStatus::PendingBusiness,
            'responsibility' => CancellationResponsibility::Customer,
            'review_status' => CancellationReviewStatus::NotRequired,
            'reviewed_by_user_id' => null,
            'review_notes' => null,
            'cancelled_at' => now(),
            'reviewed_at' => null,
        ];
    }
}
