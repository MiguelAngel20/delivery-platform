<?php

namespace App\Http\Controllers\Web\Business;

use App\Actions\Catalog\CreateProduct;
use App\Actions\Catalog\UpdateProduct;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Business\Concerns\ResolvesBusinessCatalog;
use App\Http\Requests\Business\Catalog\StoreProductRequest;
use App\Http\Requests\Business\Catalog\UpdateProductRequest;
use App\Models\Business;
use App\Models\Product;
use App\Support\CatalogData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    use ResolvesBusinessCatalog;

    public function index(Request $request): Response
    {
        $business = $this->currentBusiness($request);
        $this->authorize('viewAny', Product::class);

        $branchIds = $business->branches()->pluck('id');

        $products = Product::query()
            ->whereIn('branch_id', $branchIds)
            ->with(['category:id,name', 'currentPrice', 'branch:id,name'])
            ->when(
                filled($request->string('search')->toString()),
                fn ($query) => $query->where('name', 'like', '%'.$request->string('search')->toString().'%'),
            )
            ->when(
                filled($request->input('branch_id')),
                fn ($query) => $query->where('branch_id', $request->integer('branch_id')),
            )
            ->when(
                filled($request->input('product_category_id')),
                fn ($query) => $query->where('product_category_id', $request->integer('product_category_id')),
            )
            ->when(
                $request->input('is_available') !== null && $request->input('is_available') !== '',
                fn ($query) => $query->where('is_available', filter_var($request->input('is_available'), FILTER_VALIDATE_BOOLEAN)),
            )
            ->when(
                $request->input('is_active') !== null && $request->input('is_active') !== '',
                fn ($query) => $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN)),
            )
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Product $product): array => CatalogData::productListRow($product));

        return Inertia::render('business/products/index', [
            'products' => $products,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'branch_id' => $request->input('branch_id', ''),
                'product_category_id' => $request->input('product_category_id', ''),
                'is_available' => $request->input('is_available', ''),
                'is_active' => $request->input('is_active', ''),
            ],
            'options' => CatalogData::formOptions($business),
        ]);
    }

    public function create(Request $request): Response
    {
        $business = $this->currentBusiness($request);
        $this->authorize('create', Product::class);

        return Inertia::render('business/products/create', [
            'options' => CatalogData::formOptions($business),
        ]);
    }

    public function store(
        StoreProductRequest $request,
        CreateProduct $action,
    ): RedirectResponse {
        $business = $this->currentBusiness($request);
        $branch = $this->resolveBranch($request, $business, $request->validated('branch_id'));

        $product = $action->handle($branch, $request->validated(), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Producto creado correctamente.',
        ]);

        return to_route('business.products.edit', $product);
    }

    public function edit(Request $request, Product $product): Response
    {
        $business = $this->currentBusiness($request);
        $this->ensureProductBelongsToBusiness($business, $product);
        $this->authorize('update', $product);

        return Inertia::render('business/products/edit', [
            'product' => CatalogData::product($product),
            'options' => CatalogData::formOptions($business),
        ]);
    }

    public function update(
        UpdateProductRequest $request,
        Product $product,
        UpdateProduct $action,
    ): RedirectResponse {
        $business = $this->currentBusiness($request);
        $this->ensureProductBelongsToBusiness($business, $product);

        $action->handle($product, $request->validated(), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Producto actualizado correctamente.',
        ]);

        return to_route('business.products.edit', $product);
    }

    public function customization(Request $request, Product $product): JsonResponse
    {
        $business = $this->currentBusiness($request);
        $this->ensureProductBelongsToBusiness($business, $product);
        $this->authorize('update', $product);

        return response()->json([
            'option_groups' => CatalogData::productOptionGroups($product),
        ]);
    }

    private function ensureProductBelongsToBusiness(Business $business, Product $product): void
    {
        abort_unless(
            $business->branches()->whereKey($product->branch_id)->exists(),
            404,
        );
    }
}
