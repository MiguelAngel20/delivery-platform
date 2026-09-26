<?php

namespace App\Http\Controllers\Web\Public;

use App\Enums\BranchStatus;
use App\Enums\BusinessStatus;
use App\Http\Controllers\Controller;
use App\Models\BusinessBranch;
use App\Models\Product;
use App\Models\Promotion;
use App\Support\BusinessHours;
use App\Support\BusinessLogoStorage;
use App\Support\StorefrontProductData;
use App\Support\StorefrontPromotionData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        private readonly BusinessLogoStorage $logoStorage,
    ) {}

    public function branchOrderingStatus(BusinessBranch $branch): JsonResponse
    {
        abort_if($branch->status !== BranchStatus::Active, 404);
        $branch->loadMissing('business');
        abort_if($branch->business?->status !== BusinessStatus::Active, 404);

        $hours = $branch->opening_hours;
        $isOpen = BusinessHours::isOpenNow($hours);

        return response()->json([
            'open' => $isOpen,
            'closed_message' => $isOpen ? null : BusinessHours::closedNotice($hours),
        ]);
    }

    public function product(Product $product): JsonResponse
    {
        $product->load([
            'optionGroups' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('sort_order'),
            'optionGroups.options' => fn ($query) => $query
                ->where('is_available', true)
                ->orderBy('sort_order'),
            'optionGroups.clusters' => fn ($query) => $query->orderBy('sort_order'),
            'optionGroups.clusters.options' => fn ($query) => $query
                ->where('is_available', true)
                ->orderBy('sort_order'),
            'currentPrice',
            'category',
            'category.parent',
            'branch.business',
        ]);

        abort_if(! $product->is_active || ! $product->is_available, 404);
        abort_if($product->branch->status !== BranchStatus::Active, 404);
        abort_if($product->branch->business->status !== BusinessStatus::Active, 404);
        abort_if(
            $product->category !== null && ! $product->category->isVisibleNow(),
            404,
        );

        $business = $product->branch->business;

        return response()->json([
            'product' => StorefrontProductData::menuProduct(
                $product,
                $business->slug,
                $this->logoStorage->url($business->logo_path),
            ),
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

        abort_unless($promotion->isCurrentlyAvailable(), 404);
        abort_if($promotion->branch->status !== BranchStatus::Active, 404);
        abort_if($promotion->branch->business->status !== BusinessStatus::Active, 404);

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

    public function promotionsAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'max:50'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        /** @var list<int> $ids */
        $ids = array_values(array_map('intval', $validated['ids']));

        $availableIds = Promotion::query()
            ->currentlyAvailable()
            ->whereIn('id', $ids)
            ->whereHas('branch', fn ($query) => $query->where('status', BranchStatus::Active))
            ->whereHas('branch.business', fn ($query) => $query->where('status', BusinessStatus::Active))
            ->get()
            ->filter(fn (Promotion $promotion): bool => $promotion->isCurrentlyAvailable())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        return response()->json([
            'available_ids' => $availableIds,
        ]);
    }

    public function productsAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'max:50'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        /** @var list<int> $ids */
        $ids = array_values(array_map('intval', $validated['ids']));

        $availableIds = Product::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->where('is_available', true)
            ->whereHas('branch', fn ($query) => $query->where('status', BranchStatus::Active))
            ->whereHas('branch.business', fn ($query) => $query->where('status', BusinessStatus::Active))
            ->with(['category.parent'])
            ->get()
            ->filter(fn (Product $product): bool => $product->category === null || $product->category->isVisibleNow())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        return response()->json([
            'available_ids' => $availableIds,
        ]);
    }
}
