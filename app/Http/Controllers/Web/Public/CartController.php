<?php

namespace App\Http\Controllers\Web\Public;

use App\Enums\BranchStatus;
use App\Enums\BusinessStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\StorefrontProductData;
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
}
