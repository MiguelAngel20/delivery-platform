<?php

namespace App\Http\Controllers\Web\Business;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Business\Concerns\ResolvesBusinessCatalog;
use App\Http\Requests\Business\Catalog\StoreProductCategoryRequest;
use App\Http\Requests\Business\Catalog\UpdateProductCategoryRequest;
use App\Models\Business;
use App\Models\ProductCategory;
use App\Support\CatalogData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SubcategoryController extends Controller
{
    use ResolvesBusinessCatalog;

    public function index(Request $request): Response
    {
        $business = $this->currentBusiness($request);
        $this->authorize('viewAny', ProductCategory::class);

        $branchIds = $business->branches()->pluck('id');

        $subcategories = ProductCategory::query()
            ->whereIn('branch_id', $branchIds)
            ->whereNotNull('parent_id')
            ->with(['branch:id,name', 'parent:id,name'])
            ->when(
                filled($request->string('search')->toString()),
                fn ($query) => $query->where('name', 'like', '%'.$request->string('search')->toString().'%'),
            )
            ->when(
                filled($request->input('branch_id')),
                fn ($query) => $query->where('branch_id', $request->integer('branch_id')),
            )
            ->when(
                filled($request->input('parent_id')),
                fn ($query) => $query->where('parent_id', $request->integer('parent_id')),
            )
            ->when(
                $request->input('is_active') !== null && $request->input('is_active') !== '',
                fn ($query) => $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN)),
            )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (ProductCategory $category): array => CatalogData::category($category));

        return Inertia::render('business/subcategories/index', [
            'subcategories' => $subcategories,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'branch_id' => $request->input('branch_id', ''),
                'parent_id' => $request->input('parent_id', ''),
                'is_active' => $request->input('is_active', ''),
            ],
            'options' => CatalogData::formOptions($business),
        ]);
    }

    public function create(Request $request): Response
    {
        $business = $this->currentBusiness($request);
        $this->authorize('create', ProductCategory::class);

        return Inertia::render('business/subcategories/create', [
            'options' => CatalogData::formOptions($business),
        ]);
    }

    public function store(StoreProductCategoryRequest $request): RedirectResponse
    {
        $business = $this->currentBusiness($request);
        $branch = $this->resolveBranch($request, $business, $request->validated('branch_id'));

        if (! $request->filled('parent_id')) {
            throw ValidationException::withMessages([
                'parent_id' => 'Selecciona la categoría principal.',
            ]);
        }

        ProductCategory::query()->create([
            ...$request->safe()->except('branch_id'),
            'branch_id' => $branch->id,
            'parent_id' => $request->integer('parent_id'),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $request->integer('sort_order', 0),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Subcategoría creada correctamente.',
        ]);

        return to_route('business.subcategories.index');
    }

    public function edit(Request $request, ProductCategory $subcategory): Response
    {
        $business = $this->currentBusiness($request);
        $this->ensureCategoryBelongsToBusiness($business, $subcategory);
        abort_unless($subcategory->isSubcategory(), 404);
        $this->authorize('update', $subcategory);

        $subcategory->load(['branch:id,name', 'parent:id,name']);

        return Inertia::render('business/subcategories/edit', [
            'subcategory' => CatalogData::category($subcategory),
            'options' => CatalogData::formOptions($business),
        ]);
    }

    public function update(
        UpdateProductCategoryRequest $request,
        ProductCategory $subcategory,
    ): RedirectResponse {
        $business = $this->currentBusiness($request);
        $this->ensureCategoryBelongsToBusiness($business, $subcategory);
        abort_unless($subcategory->isSubcategory(), 404);

        if (! $request->filled('parent_id')) {
            throw ValidationException::withMessages([
                'parent_id' => 'Selecciona la categoría principal.',
            ]);
        }

        $subcategory->update([
            ...$request->safe()->only(['name', 'description', 'sort_order', 'is_active', 'parent_id']),
            'parent_id' => $request->integer('parent_id'),
            'is_active' => $request->boolean('is_active', $subcategory->is_active),
            'sort_order' => $request->integer('sort_order', $subcategory->sort_order),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Subcategoría actualizada correctamente.',
        ]);

        return to_route('business.subcategories.index');
    }

    public function deactivate(Request $request, ProductCategory $subcategory): RedirectResponse
    {
        $business = $this->currentBusiness($request);
        $this->ensureCategoryBelongsToBusiness($business, $subcategory);
        abort_unless($subcategory->isSubcategory(), 404);
        $this->authorize('update', $subcategory);

        $subcategory->update(['is_active' => false]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Subcategoría desactivada.',
        ]);

        return back();
    }

    public function activate(Request $request, ProductCategory $subcategory): RedirectResponse
    {
        $business = $this->currentBusiness($request);
        $this->ensureCategoryBelongsToBusiness($business, $subcategory);
        abort_unless($subcategory->isSubcategory(), 404);
        $this->authorize('update', $subcategory);

        $subcategory->update(['is_active' => true]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Subcategoría activada.',
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
