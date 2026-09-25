<?php

namespace App\Http\Controllers\Web\Business;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Business\Concerns\ResolvesBusinessCatalog;
use App\Http\Requests\Business\Catalog\StoreProductCategoryRequest;
use App\Http\Requests\Business\Catalog\UpdateProductCategoryRequest;
use App\Models\Business;
use App\Models\ProductCategory;
use App\Support\Catalog\CatalogListPagination;
use App\Support\CatalogData;
use App\Support\CategorySchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    use ResolvesBusinessCatalog;

    public function index(Request $request): Response
    {
        $business = $this->currentBusiness($request);
        $this->authorize('viewAny', ProductCategory::class);

        $branchIds = $business->branches()->pluck('id');

        $perPage = CatalogListPagination::perPage($request);

        $categories = ProductCategory::query()
            ->whereIn('branch_id', $branchIds)
            ->roots()
            ->with(['branch:id,name'])
            ->withCount(['products', 'children'])
            ->when(
                filled($request->string('search')->toString()),
                fn ($query) => $query->where('name', 'like', '%'.$request->string('search')->toString().'%'),
            )
            ->when(
                filled($request->input('branch_id')),
                fn ($query) => $query->where('branch_id', $request->integer('branch_id')),
            )
            ->when(
                $request->input('is_active') !== null && $request->input('is_active') !== '',
                fn ($query) => $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN)),
            )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ProductCategory $category): array => CatalogData::category($category));

        return Inertia::render('business/categories/index', [
            'categories' => $categories,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'branch_id' => $request->input('branch_id', ''),
                'is_active' => $request->input('is_active', ''),
                'per_page' => $perPage,
            ],
            'options' => CatalogData::formOptions($business),
        ]);
    }

    public function create(Request $request): Response
    {
        $business = $this->currentBusiness($request);
        $this->authorize('create', ProductCategory::class);

        return Inertia::render('business/categories/create', [
            'options' => CatalogData::formOptions($business),
        ]);
    }

    public function store(StoreProductCategoryRequest $request): RedirectResponse
    {
        $business = $this->currentBusiness($request);
        $branch = $this->resolveBranch($request, $business, $request->validated('branch_id'));

        ProductCategory::query()->create([
            ...$request->safe()->except(['branch_id', 'parent_id', 'has_schedule', 'schedule_hours']),
            'branch_id' => $branch->id,
            'parent_id' => null,
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $request->integer('sort_order', 0),
            ...CategorySchedule::attributesFromValidated($request->validated(), allowSchedule: true),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Categoría creada correctamente.',
        ]);

        return to_route('business.categories.index');
    }

    public function edit(Request $request, ProductCategory $category): Response
    {
        $business = $this->currentBusiness($request);
        $this->ensureCategoryBelongsToBusiness($business, $category);
        abort_unless($category->isRoot(), 404);
        $this->authorize('update', $category);

        $category->load(['branch:id,name']);

        return Inertia::render('business/categories/edit', [
            'category' => CatalogData::category($category),
            'options' => CatalogData::formOptions($business),
        ]);
    }

    public function update(
        UpdateProductCategoryRequest $request,
        ProductCategory $category,
    ): RedirectResponse {
        $business = $this->currentBusiness($request);
        $this->ensureCategoryBelongsToBusiness($business, $category);
        abort_unless($category->isRoot(), 404);

        $category->update([
            ...$request->safe()->only(['name', 'description', 'sort_order', 'is_active']),
            'parent_id' => null,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $request->integer('sort_order', $category->sort_order),
            ...CategorySchedule::attributesFromValidated($request->validated(), allowSchedule: true),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Categoría actualizada correctamente.',
        ]);

        return to_route('business.categories.index');
    }

    public function destroy(Request $request, ProductCategory $category): RedirectResponse
    {
        $business = $this->currentBusiness($request);
        $this->ensureCategoryBelongsToBusiness($business, $category);
        abort_unless($category->isRoot(), 404);
        $this->authorize('delete', $category);

        if ($category->isInUse()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'No se puede eliminar: la categoría tiene productos o subcategorías asociadas.',
            ]);

            return back();
        }

        $category->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Categoría eliminada.',
        ]);

        return back();
    }

    public function deactivate(Request $request, ProductCategory $category): RedirectResponse
    {
        $business = $this->currentBusiness($request);
        $this->ensureCategoryBelongsToBusiness($business, $category);
        $this->authorize('update', $category);

        $category->update(['is_active' => false]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Categoría desactivada.',
        ]);

        return back();
    }

    public function activate(Request $request, ProductCategory $category): RedirectResponse
    {
        $business = $this->currentBusiness($request);
        $this->ensureCategoryBelongsToBusiness($business, $category);
        $this->authorize('update', $category);

        $category->update(['is_active' => true]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Categoría activada.',
        ]);

        return back();
    }

    private function ensureCategoryBelongsToBusiness(Business $business, ProductCategory $category): void
    {
        abort_unless(
            $business->branches()->whereKey($category->branch_id)->exists(),
            404,
        );
    }
}
