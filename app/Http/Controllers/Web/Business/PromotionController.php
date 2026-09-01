<?php

namespace App\Http\Controllers\Web\Business;

use App\Actions\Catalog\CreatePromotion;
use App\Actions\Catalog\UpdatePromotion;
use App\Enums\PromotionStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Business\Concerns\ResolvesBusinessCatalog;
use App\Http\Requests\Business\Catalog\StorePromotionRequest;
use App\Http\Requests\Business\Catalog\UpdatePromotionRequest;
use App\Models\Business;
use App\Models\Promotion;
use App\Support\CatalogData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
    use ResolvesBusinessCatalog;

    public function index(Request $request): Response
    {
        $business = $this->currentBusiness($request);
        $this->authorize('viewAny', Promotion::class);

        $branchIds = $business->branches()->pluck('id');

        $promotions = Promotion::query()
            ->whereIn('branch_id', $branchIds)
            ->with(['branch:id,name', 'items'])
            ->when(
                filled($request->string('search')->toString()),
                fn ($query) => $query->where('name', 'like', '%'.$request->string('search')->toString().'%'),
            )
            ->when(
                filled($request->input('branch_id')),
                fn ($query) => $query->where('branch_id', $request->integer('branch_id')),
            )
            ->when(
                filled($request->input('status')),
                fn ($query) => $query->where('status', $request->string('status')->toString()),
            )
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Promotion $promotion): array => CatalogData::promotion($promotion));

        return Inertia::render('business/promotions/index', [
            'promotions' => $promotions,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'branch_id' => $request->input('branch_id', ''),
                'status' => $request->input('status', ''),
            ],
            'options' => CatalogData::formOptions($business),
        ]);
    }

    public function create(Request $request): Response
    {
        $business = $this->currentBusiness($request);
        $this->authorize('create', Promotion::class);

        return Inertia::render('business/promotions/create', [
            'options' => CatalogData::formOptions($business),
        ]);
    }

    public function store(
        StorePromotionRequest $request,
        CreatePromotion $action,
    ): RedirectResponse {
        $business = $this->currentBusiness($request);
        $branch = $this->resolveBranch($request, $business, $request->validated('branch_id'));

        $promotion = $action->handle($branch, $request->validated(), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Promoción creada correctamente.',
        ]);

        return to_route('business.promotions.index');
    }

    public function edit(Request $request, Promotion $promotion): Response
    {
        $business = $this->currentBusiness($request);
        $this->ensurePromotionBelongsToBusiness($business, $promotion);
        $this->authorize('update', $promotion);

        return Inertia::render('business/promotions/edit', [
            'promotion' => CatalogData::promotion($promotion),
            'options' => CatalogData::formOptions($business),
        ]);
    }

    public function update(
        UpdatePromotionRequest $request,
        Promotion $promotion,
        UpdatePromotion $action,
    ): RedirectResponse {
        $business = $this->currentBusiness($request);
        $this->ensurePromotionBelongsToBusiness($business, $promotion);

        $action->handle($promotion, $request->validated(), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Promoción actualizada correctamente.',
        ]);

        return to_route('business.promotions.index');
    }

    public function pause(Request $request, Promotion $promotion): RedirectResponse
    {
        return $this->setStatus($request, $promotion, PromotionStatus::Paused, 'Promoción pausada.');
    }

    public function activate(Request $request, Promotion $promotion): RedirectResponse
    {
        return $this->setStatus($request, $promotion, PromotionStatus::Active, 'Promoción activada.');
    }

    public function archive(Request $request, Promotion $promotion): RedirectResponse
    {
        $business = $this->currentBusiness($request);
        $this->ensurePromotionBelongsToBusiness($business, $promotion);
        $this->authorize('update', $promotion);

        $promotion->update(['status' => PromotionStatus::Expired]);
        $promotion->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Promoción archivada.',
        ]);

        return to_route('business.promotions.index');
    }

    private function setStatus(
        Request $request,
        Promotion $promotion,
        PromotionStatus $status,
        string $message,
    ): RedirectResponse {
        $business = $this->currentBusiness($request);
        $this->ensurePromotionBelongsToBusiness($business, $promotion);
        $this->authorize('update', $promotion);

        $promotion->update(['status' => $status]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $message,
        ]);

        return back();
    }

    private function ensurePromotionBelongsToBusiness(Business $business, Promotion $promotion): void
    {
        abort_unless(
            $business->branches()->whereKey($promotion->branch_id)->exists(),
            404,
        );
    }
}
