<?php

namespace App\Http\Controllers\Web\Public;

use App\Enums\BranchStatus;
use App\Enums\BusinessStatus;
use App\Enums\PromotionStatus;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Product;
use App\Models\Promotion;
use App\Support\BusinessHours;
use App\Support\BusinessLogoStorage;
use App\Support\BusinessTypes;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __construct(
        private readonly BusinessLogoStorage $logoStorage,
    ) {}

    public function __invoke(Request $request): Response
    {
        $restaurants = Business::query()
            ->where('status', BusinessStatus::Active)
            ->whereHas('branches', fn ($query) => $query->where('status', BranchStatus::Active))
            ->with(['branches' => fn ($query) => $query
                ->where('status', BranchStatus::Active)
                ->orderBy('name')
                ->limit(1)])
            ->orderBy('name')
            ->get()
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
                ];
            })
            ->values()
            ->all();

        $products = Product::query()
            ->where('is_active', true)
            ->whereHas('branch', function ($query): void {
                $query
                    ->where('status', BranchStatus::Active)
                    ->whereHas('business', fn ($businessQuery) => $businessQuery
                        ->where('status', BusinessStatus::Active));
            })
            ->with([
                'branch.business:id,slug',
                'category:id,name',
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product): array => [
                'id' => (string) $product->id,
                'restaurantSlug' => $product->branch?->business?->slug,
                'category' => $product->category?->name ?? 'Sin categoría',
                'name' => $product->name,
                'description' => $product->description ?? '',
                'ingredients' => [],
            ])
            ->values()
            ->all();

        $promotions = Promotion::query()
            ->where('status', PromotionStatus::Active)
            ->with(['branch.business:id,slug', 'items'])
            ->latest()
            ->get()
            ->map(fn (Promotion $promotion): array => [
                'id' => (string) $promotion->id,
                'restaurantSlug' => $promotion->branch?->business?->slug,
                'name' => $promotion->name,
                'description' => $promotion->description ?? '',
                'composition' => $promotion->items->pluck('name')->implode(' + '),
            ])
            ->values()
            ->all();

        return Inertia::render('public/search/index', [
            'q' => $request->string('q')->toString(),
            'restaurants' => $restaurants,
            'products' => $products,
            'promotions' => $promotions,
            'categories' => BusinessTypes::categories(),
        ]);
    }
}
