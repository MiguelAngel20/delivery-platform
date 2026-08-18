<?php

namespace App\Http\Controllers\Web\Public;

use App\Enums\BranchStatus;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Enums\PromotionStatus;
use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
    public function __invoke(): Response
    {
        $promotions = Promotion::query()
            ->where('status', PromotionStatus::Active)
            ->whereHas('branch', fn ($query) => $query->where('status', BranchStatus::Active))
            ->whereHas('branch.business', fn ($query) => $query
                ->where('status', BusinessStatus::Active)
                ->whereNotNull('slug'))
            ->with(['branch.business', 'items'])
            ->latest()
            ->get()
            ->sortBy(function (Promotion $promotion): array {
                $isPartner = $promotion->branch?->business?->operation_mode
                    === BusinessOperationMode::Partner;

                return [
                    $isPartner ? 0 : 1,
                    -1 * ($promotion->created_at?->getTimestamp() ?? 0),
                ];
            })
            ->values()
            ->map(fn (Promotion $promotion): array => [
                'id' => (string) $promotion->id,
                'restaurantSlug' => $promotion->branch?->business?->slug,
                'restaurant_name' => $promotion->branch?->business?->name,
                'business_type' => $promotion->branch?->business?->business_type,
                'name' => $promotion->name,
                'description' => $promotion->description ?? '',
                'price' => (float) $promotion->promotion_price,
                'composition' => $promotion->items->pluck('name')->implode(' + '),
                'image_url' => $promotion->imageUrl(),
                'is_affiliated' => $promotion->branch?->business?->operation_mode
                    === BusinessOperationMode::Partner,
            ])
            ->values()
            ->all();

        return Inertia::render('public/promotions/index', [
            'promotions' => $promotions,
        ]);
    }
}
