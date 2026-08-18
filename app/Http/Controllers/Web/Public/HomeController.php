<?php

namespace App\Http\Controllers\Web\Public;

use App\Enums\BranchStatus;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Enums\PromotionStatus;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Promotion;
use App\Support\BusinessBannerStorage;
use App\Support\BusinessHours;
use App\Support\BusinessLogoStorage;
use App\Support\BusinessTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(
        private readonly BusinessLogoStorage $logoStorage,
        private readonly BusinessBannerStorage $bannerStorage,
    ) {}

    public function __invoke(Request $request): Response
    {
        $categorySlug = $request->string('category')->toString();
        $selectedType = BusinessTypes::findBySlug($categorySlug !== '' ? $categorySlug : null);

        $restaurantsQuery = Business::query()
            ->where('status', BusinessStatus::Active)
            ->whereHas('branches', fn ($query) => $query->where('status', BranchStatus::Active))
            ->with(['branches' => fn ($query) => $query
                ->where('status', BranchStatus::Active)
                ->orderBy('name')
                ->limit(1)]);

        if ($selectedType !== null) {
            $restaurantsQuery->where('business_type', $selectedType);
        }

        $restaurants = $restaurantsQuery
            ->get()
            ->sortBy(fn (Business $business): array => [
                $business->operation_mode === BusinessOperationMode::Partner ? 0 : 1,
                $business->name,
            ])
            ->values()
            ->map(function (Business $business): array {
                $branch = $business->branches->first();
                $hours = $branch?->opening_hours;
                $isOpen = BusinessHours::isOpenNow($hours);
                $canAcceptOrders = $business->operation_mode->canAcceptOrders();

                return [
                    'id' => (string) $business->id,
                    'slug' => $business->slug,
                    'name' => $business->name,
                    'category' => $business->business_type ?? 'Restaurante',
                    'eta' => '25-35 min',
                    'open' => $isOpen,
                    'mode' => $business->operation_mode->value,
                    'branchName' => $branch?->name ?? 'Sucursal',
                    'schedule' => BusinessHours::todayLabel($hours),
                    'canOrder' => $canAcceptOrders && $isOpen,
                    'modeLabel' => ! $isOpen
                        ? 'Cerrado ahora'
                        : ($canAcceptOrders
                            ? 'Entrega disponible'
                            : 'Solo información'),
                    'logo_url' => $this->logoStorage->url($business->logo_path),
                    'is_affiliated' => $business->operation_mode === BusinessOperationMode::Partner,
                ];
            })
            ->values()
            ->all();

        $affiliatedPartners = Business::query()
            ->where('status', BusinessStatus::Active)
            ->where('operation_mode', BusinessOperationMode::Partner)
            ->whereNotNull('banner_path')
            ->where('banner_path', '!=', '')
            ->whereHas('branches', fn ($query) => $query->where('status', BranchStatus::Active))
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'banner_path'])
            ->map(fn (Business $business): array => [
                'id' => (string) $business->id,
                'slug' => $business->slug,
                'name' => $business->name,
                'banner_url' => $this->bannerStorage->url($business->banner_path),
            ])
            ->filter(fn (array $partner): bool => filled($partner['banner_url'] ?? null))
            ->values()
            ->all();

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
            ->take(24)
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

        return Inertia::render('public/home', [
            'restaurants' => $restaurants,
            'affiliatedPartners' => $affiliatedPartners,
            'promotions' => $promotions,
            'filters' => [
                'category' => $selectedType !== null ? Str::slug($selectedType) : null,
            ],
        ]);
    }
}
