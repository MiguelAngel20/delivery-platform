<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PricingAdjustmentType;
use App\Models\BusinessBranch;
use App\Models\PricingRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PricingRule>
 */
class PricingRuleFactory extends Factory
{
    protected $model = PricingRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => BusinessBranch::factory(),
            'product_id' => null,
            'payment_method' => PaymentMethod::Cash,
            'adjustment_type' => PricingAdjustmentType::FixedDiscount,
            'adjustment_value' => 5,
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
            'created_by_user_id' => null,
        ];
    }
}
