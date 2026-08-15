<?php

namespace App\Http\Controllers\Web\Public;

use App\Enums\BranchStatus;
use App\Enums\BusinessStatus;
use App\Enums\PromotionStatus;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Promotion;
use App\Support\BusinessLogoStorage;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(
        private readonly BusinessLogoStorage $logoStorage,
    ) {}

    public function __invoke(): Response
    {
        $restaurants = Business::query()
            ->where('status', BusinessStatus::Active)
            ->whereHas('branches', fn ($query) => $query->where('status', BranchStatus::Active))
            ->with(['branches' => fn ($query) => $query
                ->where('status', BranchStatus::Active)
                ->orderBy('name')
                ->limit(1)])
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(function (Business $business): array {
                $branch = $business->branches->first();

                return [
                    'id' => (string) $business->id,
                    'slug' => $business->slug,
                    'name' => $business->name,
                    'category' => $business->business_type ?? 'Restaurante',
                    'eta' => '25-35 min',
                    'open' => true,
                    'mode' => $business->operation_mode->value,
                    'branchName' => $branch?->name ?? 'Sucursal',
                    'schedule' => 'Consulta horario en sucursal',
                    'canOrder' => $business->operation_mode->canAcceptOrders(),
                    'modeLabel' => $business->operation_mode->canAcceptOrders()
                        ? 'Entrega disponible'
                        : 'Solo información',
                    'logo_url' => $this->logoStorage->url($business->logo_path),
                ];
            })
            ->values()
            ->all();

        $promotions = Promotion::query()
            ->where('status', PromotionStatus::Active)
            ->with(['branch.business', 'items'])
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Promotion $promotion): array => [
                'id' => (string) $promotion->id,
                'restaurantSlug' => $promotion->branch?->business?->slug,
                'name' => $promotion->name,
                'description' => $promotion->description ?? '',
                'price' => (float) $promotion->promotion_price,
                'composition' => $promotion->items->pluck('name')->implode(' + '),
                'image_url' => $promotion->imageUrl(),
            ])
            ->values()
            ->all();

        return Inertia::render('public/home', [
            'restaurants' => $restaurants,
            'promotions' => $promotions,
        ]);
    }
}
