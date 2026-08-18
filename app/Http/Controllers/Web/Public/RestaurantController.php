<?php

namespace App\Http\Controllers\Web\Public;

use App\Enums\BranchStatus;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Enums\PromotionStatus;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Promotion;
use App\Services\Geo\BranchResolverService;
use App\Services\Geo\CoverageService;
use App\Services\Geo\DeliveryEstimateService;
use App\Services\Geo\DistanceService;
use App\Support\BusinessHours;
use App\Support\BusinessLogoStorage;
use App\Support\GeoPoint;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RestaurantController extends Controller
{
    public function __construct(
        private readonly BusinessLogoStorage $logoStorage,
        private readonly BranchResolverService $branchResolver,
        private readonly CoverageService $coverage,
        private readonly DistanceService $distance,
        private readonly DeliveryEstimateService $estimates,
    ) {}

    public function index(Request $request): Response
    {
        $latitude = $request->filled('lat') ? (float) $request->input('lat') : null;
        $longitude = $request->filled('lng') ? (float) $request->input('lng') : null;
        $hasLocation = $latitude !== null && $longitude !== null;

        $restaurants = Business::query()
            ->where('status', BusinessStatus::Active)
            ->whereHas('branches', fn ($query) => $query->where('status', BranchStatus::Active))
            ->with(['branches' => fn ($query) => $query
                ->where('status', BranchStatus::Active)
                ->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->map(function (Business $business) use ($hasLocation, $latitude, $longitude): array {
                $branch = null;
                $inCoverage = true;
                $distanceMeters = null;
                $eta = null;

                if ($hasLocation) {
                    $branch = $this->branchResolver->resolveBestBranch($business, $latitude, $longitude);
                    $inCoverage = $branch !== null;

                    if ($branch !== null) {
                        $distanceMeters = $this->distance->haversineMeters(
                            GeoPoint::make($branch->latitude, $branch->longitude),
                            GeoPoint::make($latitude, $longitude),
                        );
                        $estimate = $this->estimates->estimateForBranchDelivery(
                            $branch,
                            $latitude,
                            $longitude,
                        );
                        $eta = $estimate
                            ? "{$estimate['minutes_min']}-{$estimate['minutes_max']} min"
                            : null;
                    } else {
                        $branch = $business->branches->first();
                    }
                } else {
                    $branch = $business->branches->first();
                }

                return [
                    ...$this->restaurantCard($business, $branch, $eta, $inCoverage, $distanceMeters),
                ];
            })
            ->when($hasLocation, fn ($collection) => $collection
                ->sortBy(fn (array $row): array => [
                    ($row['is_affiliated'] ?? false) ? 0 : 1,
                    ($row['in_coverage'] ?? false) ? 0 : 1,
                    $row['distance_meters'] ?? PHP_INT_MAX,
                    $row['name'] ?? '',
                ])
                ->values(), fn ($collection) => $collection
                ->sortBy(fn (array $row): array => [
                    ($row['is_affiliated'] ?? false) ? 0 : 1,
                    $row['name'] ?? '',
                ])
                ->values())
            ->values();

        return Inertia::render('public/restaurants/index', [
            'restaurants' => [
                'data' => $restaurants->all(),
            ],
            'filters' => [
                'search' => $request->string('search')->toString(),
                'lat' => $latitude,
                'lng' => $longitude,
            ],
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $business = Business::query()
            ->where('slug', $slug)
            ->where('status', BusinessStatus::Active)
            ->firstOrFail();

        $branch = $this->resolveBranch($request, $business);

        $categories = ProductCategory::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'sort_order']);

        $products = Product::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->with([
                'category:id,name',
                'currentPrice',
                'optionGroups' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order'),
                'optionGroups.options' => fn ($query) => $query->where('is_available', true)->orderBy('sort_order'),
            ])
            ->orderBy('name')
            ->get();

        $promotions = Promotion::query()
            ->where('branch_id', $branch->id)
            ->where('status', PromotionStatus::Active)
            ->with('items')
            ->latest()
            ->get();

        $latitude = $request->filled('lat') ? (float) $request->input('lat') : null;
        $longitude = $request->filled('lng') ? (float) $request->input('lng') : null;
        $inCoverage = true;
        $eta = null;

        if ($latitude !== null && $longitude !== null) {
            $inCoverage = $this->coverage->isPointCovered($latitude, $longitude, $branch);
            $estimate = $this->estimates->estimateForBranchDelivery($branch, $latitude, $longitude);
            $eta = $estimate
                ? "{$estimate['minutes_min']}-{$estimate['minutes_max']} min"
                : null;
        }

        return Inertia::render('public/restaurants/show', [
            'restaurant' => [
                ...$this->restaurantCard($business, $branch, $eta, $inCoverage),
                'description' => $business->description,
                'branches' => $business->branches()
                    ->where('status', BranchStatus::Active)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (BusinessBranch $item): array => [
                        'id' => $item->id,
                        'name' => $item->name,
                    ])
                    ->values()
                    ->all(),
            ],
            'branch_id' => $branch->id,
            'categories' => $categories->map(fn (ProductCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
            ])->values()->all(),
            'products' => $products->map(fn (Product $product): array => $this->productPayload($product, $business->slug))->values()->all(),
            'promotions' => $promotions->map(fn (Promotion $promotion): array => [
                'id' => $promotion->id,
                'name' => $promotion->name,
                'description' => $promotion->description,
                'price' => (float) $promotion->promotion_price,
                'composition' => $promotion->items->pluck('name')->implode(' + '),
                'image_url' => $promotion->imageUrl(),
            ])->values()->all(),
        ]);
    }

    private function resolveBranch(Request $request, Business $business): BusinessBranch
    {
        $branchQuery = $business->branches()->where('status', BranchStatus::Active);

        if ($request->filled('branch')) {
            return (clone $branchQuery)->whereKey($request->integer('branch'))->firstOrFail();
        }

        if ($request->filled('lat') && $request->filled('lng')) {
            $resolved = $this->branchResolver->resolveBestBranch(
                $business,
                (float) $request->input('lat'),
                (float) $request->input('lng'),
            );

            if ($resolved !== null) {
                return $resolved;
            }
        }

        return $branchQuery->orderBy('name')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function restaurantCard(
        Business $business,
        ?BusinessBranch $branch = null,
        ?string $eta = null,
        bool $inCoverage = true,
        ?int $distanceMeters = null,
    ): array {
        $branch ??= $business->branches->first();
        $hours = $branch?->opening_hours;
        $isOpen = BusinessHours::isOpenNow($hours);
        $canAcceptOrders = $business->operation_mode->canAcceptOrders();

        return [
            'id' => (string) $business->id,
            'slug' => $business->slug,
            'name' => $business->name,
            'category' => $business->business_type ?? 'Restaurante',
            'eta' => $eta,
            'open' => $isOpen,
            'mode' => $business->operation_mode->value,
            'branchName' => $branch?->name ?? 'Sucursal',
            'schedule' => BusinessHours::todayLabel($hours),
            'canOrder' => $canAcceptOrders && $inCoverage && $isOpen,
            'in_coverage' => $inCoverage,
            'distance_meters' => $distanceMeters,
            'modeLabel' => ! $isOpen
                ? 'Cerrado ahora'
                : (! $inCoverage
                    ? 'Fuera de cobertura'
                    : ($canAcceptOrders
                        ? 'Entrega disponible'
                        : 'Solo información')),
            'logo_url' => $this->logoStorage->url($business->logo_path),
            'is_affiliated' => $business->operation_mode === BusinessOperationMode::Partner,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(Product $product, string $restaurantSlug): array
    {
        return [
            'id' => $product->id,
            'restaurantSlug' => $restaurantSlug,
            'category' => $product->category?->name ?? 'Sin categoría',
            'product_category_id' => $product->product_category_id,
            'name' => $product->name,
            'description' => $product->description ?? '',
            'price' => (float) ($product->currentPrice?->list_price ?? 0),
            'image_url' => $product->imageUrl(),
            'is_available' => $product->is_available,
            'allow_special_instructions' => $product->allow_special_instructions,
            'option_groups' => $product->optionGroups->map(fn ($group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'type' => $group->type->value,
                'is_required' => $group->is_required,
                'min_selection' => $group->min_selection,
                'max_selection' => $group->max_selection,
                'options' => $group->options->map(fn ($option): array => [
                    'id' => $option->id,
                    'name' => $option->name,
                    'description' => $option->description,
                    'price_modifier' => (float) $option->price_modifier,
                    'is_default' => $option->is_default,
                ])->values()->all(),
            ])->values()->all(),
            'ingredients' => $product->optionGroups
                ->filter(fn ($group) => $group->type->value === 'removable')
                ->flatMap(fn ($group) => $group->options->where('is_default', true)->pluck('name'))
                ->values()
                ->all(),
            'extras' => $product->optionGroups
                ->filter(fn ($group) => $group->type->value === 'addon')
                ->flatMap(fn ($group) => $group->options->map(fn ($option): array => [
                    'id' => (string) $option->id,
                    'name' => $option->name,
                    'price' => (float) $option->price_modifier,
                    'option_id' => $option->id,
                    'group_id' => $group->id,
                ]))
                ->values()
                ->all(),
        ];
    }
}
