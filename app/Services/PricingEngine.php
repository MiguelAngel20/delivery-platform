<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PricingAdjustmentType;
use App\Models\PricingRule;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Pricing engine scaffold for PLATFORM_OPERATED checkout adjustments.
 * Does not implement checkout — only computes adjustments from pricing_rules.
 */
final class PricingEngine
{
    /**
     * @param  Collection<int, Product>|array<int, Product>  $products
     * @return array{subtotal: string, discount: string, total: string, rules: list<array<string, mixed>>}
     */
    public function quoteForCash(iterable $products, BusinessBranchContext $context): array
    {
        $subtotal = '0.00';
        $productIds = [];

        foreach ($products as $product) {
            $price = $product->currentPrice?->list_price ?? '0.00';
            $subtotal = bcadd($subtotal, (string) $price, 2);
            $productIds[] = $product->id;
        }

        $rules = PricingRule::query()
            ->where('branch_id', $context->branchId)
            ->where('is_active', true)
            ->where('payment_method', PaymentMethod::Cash)
            ->where(function ($query) use ($productIds): void {
                $query->whereNull('product_id')
                    ->orWhereIn('product_id', $productIds);
            })
            ->where(function ($query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->get();

        $discount = '0.00';
        $applied = [];

        foreach ($rules as $rule) {
            $amount = $this->ruleAmount($rule, $subtotal);
            $discount = bcadd($discount, $amount, 2);
            $applied[] = [
                'id' => $rule->id,
                'adjustment_type' => $rule->adjustment_type->value,
                'adjustment_value' => (string) $rule->adjustment_value,
                'amount' => $amount,
            ];
        }

        if (bccomp($discount, $subtotal, 2) === 1) {
            $discount = $subtotal;
        }

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => bcsub($subtotal, $discount, 2),
            'rules' => $applied,
        ];
    }

    private function ruleAmount(PricingRule $rule, string $subtotal): string
    {
        return match ($rule->adjustment_type) {
            PricingAdjustmentType::FixedDiscount => (string) $rule->adjustment_value,
            PricingAdjustmentType::PercentageDiscount => bcmul(
                $subtotal,
                bcdiv((string) $rule->adjustment_value, '100', 4),
                2,
            ),
        };
    }
}
