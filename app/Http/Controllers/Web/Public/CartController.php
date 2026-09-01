<?php

namespace App\Http\Controllers\Web\Public;

use App\Enums\BranchStatus;
use App\Enums\BusinessStatus;
use App\Enums\PromotionStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Promotion;
use App\Support\StorefrontProductData;
use App\Support\StorefrontPromotionData;
use Illuminate\Http\JsonResponse;

class CartController extends Controller
{
    public function product(Product $product): JsonResponse
    {
        $product->load([
            'optionGroups' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('sort_order'),
            'optionGroups.options' => fn ($query) => $query
                ->where('is_available', true)
                ->orderBy('sort_order'),
            'currentPrice',
            'category',
            'branch.business',
        ]);

        abort_if(! $product->is_active || ! $product->is_available, 404);
        abort_if($product->branch->status !== BranchStatus::Active, 404);
        abort_if($product->branch->business->status !== BusinessStatus::Active, 404);

        $business = $product->branch->business;

        return response()->json([
            'product' => StorefrontProductData::menuProduct($product, $business->slug),
            'branch_id' => $product->branch_id,
            'restaurant' => [
                'name' => $business->name,
                'slug' => $business->slug,
                'mode' => $business->operation_mode->value,
            ],
        ]);
    }

    public function promotion(Promotion $promotion): JsonResponse
    {
        $promotion->load([
            'items.product',
            'branch.business',
        ]);

        abort_if($promotion->status !== PromotionStatus::Active, 404);
        abort_if($promotion->branch->status !== BranchStatus::Active, 404);
        abort_if($promotion->branch->business->status !== BusinessStatus::Active, 404);

        if ($promotion->starts_at !== null && $promotion->starts_at->isFuture()) {
            abort(404);
        }

        if ($promotion->ends_at !== null && $promotion->ends_at->isPast()) {
            abort(404);
        }

        $business = $promotion->branch->business;

        return response()->json([
            'promotion' => StorefrontPromotionData::cartPromotion($promotion, $business->slug),
            'branch_id' => $promotion->branch_id,
            'restaurant' => [
                'name' => $business->name,
                'slug' => $business->slug,
                'mode' => $business->operation_mode->value,
            ],
        ]);
    }
}
