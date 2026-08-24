<?php

namespace App\Http\Controllers\Web\Admin\Catalog;

use App\Actions\Catalog\CreateProduct;
use App\Actions\Catalog\CreatePromotion;
use App\Actions\Catalog\UpdateProduct;
use App\Actions\Catalog\UpdatePromotion;
use App\Enums\BusinessOperationMode;
use App\Enums\PromotionStatus;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Promotion;
use App\Support\CatalogData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(Business $business): Response
    {
        $this->ensurePlatformOperated($business);

        return Inertia::render('admin/businesses/catalog/index', [
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'slug' => $business->slug,
                'operation_mode' => $business->operation_mode->value,
            ],
            'options' => CatalogData::formOptions($business),
        ]);
    }

    public function categoriesIndex(Request $request, Business $business): Response
    {
        $this->ensurePlatformOperated($business);

        $categories = ProductCategory::query()
            ->whereIn('branch_id', $business->branches()->select('id'))
            ->roots()
            ->with(['branch:id,name'])
            ->orderBy('sort_order')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (ProductCategory $category): array => CatalogData::category($category));

        return Inertia::render('admin/businesses/catalog/categories/index', [
            'business' => $this->businessPayload($business),
            'categories' => $categories,
            'options' => CatalogData::formOptions($business),
        ]);
    }

    public function categoriesStore(Request $request, Business $business): RedirectResponse
    {
        $this->ensurePlatformOperated($business);

        $validated = $request->validate([
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('business_branches', 'id')
                    ->where('business_id', $business->id)
                    ->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        ProductCategory::query()->create([
            ...$validated,
            'parent_id' => null,
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Categoría creada.']);

        return to_route('admin.businesses.catalog.categories.index', $business);
    }

    public function subcategoriesIndex(Request $request, Business $business): Response
    {
        $this->ensurePlatformOperated($business);

        $subcategories = ProductCategory::query()
            ->whereIn('branch_id', $business->branches()->select('id'))
            ->whereNotNull('parent_id')
            ->with(['branch:id,name', 'parent:id,name'])
            ->orderBy('sort_order')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (ProductCategory $category): array => CatalogData::category($category));

        return Inertia::render('admin/businesses/catalog/subcategories/index', [
            'business' => $this->businessPayload($business),
            'subcategories' => $subcategories,
            'options' => CatalogData::formOptions($business),
        ]);
    }

    public function subcategoriesStore(Request $request, Business $business): RedirectResponse
    {
        $this->ensurePlatformOperated($business);

        $validated = $request->validate([
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('business_branches', 'id')
                    ->where('business_id', $business->id)
                    ->whereNull('deleted_at'),
            ],
            'parent_id' => [
                'required',
                'integer',
                Rule::exists('product_categories', 'id')
                    ->where('branch_id', $request->integer('branch_id'))
                    ->whereNull('parent_id')
                    ->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        ProductCategory::query()->create([
            ...$validated,
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Subcategoría creada.']);

        return to_route('admin.businesses.catalog.subcategories.index', $business);
    }

    public function productsIndex(Request $request, Business $business): Response
    {
        $this->ensurePlatformOperated($business);

        $products = Product::query()
            ->whereIn('branch_id', $business->branches()->select('id'))
            ->with(['category:id,name', 'currentPrice', 'branch:id,name'])
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Product $product): array => CatalogData::productListRow($product));

        return Inertia::render('admin/businesses/catalog/products/index', [
            'business' => $this->businessPayload($business),
            'products' => $products,
            'options' => CatalogData::formOptions($business),
        ]);
    }

    public function productsCreate(Business $business): Response
    {
        $this->ensurePlatformOperated($business);

        return Inertia::render('admin/businesses/catalog/products/create', [
            'business' => $this->businessPayload($business),
            'options' => CatalogData::formOptions($business),
        ]);
    }

    public function productsStore(Request $request, Business $business, CreateProduct $action): RedirectResponse
    {
        $this->ensurePlatformOperated($business);
        $data = $this->validateProduct($request, $business);
        $branch = $this->branch($business, $data['branch_id']);
        $product = $action->handle($branch, $data, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Producto creado.']);

        return to_route('admin.businesses.catalog.products.edit', [$business, $product]);
    }

    public function productsEdit(Business $business, Product $product): Response
    {
        $this->ensurePlatformOperated($business);
        $this->ensureProduct($business, $product);

        return Inertia::render('admin/businesses/catalog/products/edit', [
            'business' => $this->businessPayload($business),
            'product' => CatalogData::product($product),
            'options' => CatalogData::formOptions($business),
        ]);
    }

    public function productsUpdate(
        Request $request,
        Business $business,
        Product $product,
        UpdateProduct $action,
    ): RedirectResponse {
        $this->ensurePlatformOperated($business);
        $this->ensureProduct($business, $product);
        $data = $this->validateProduct($request, $business, updating: true);
        $action->handle($product, $data, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Producto actualizado.']);

        return to_route('admin.businesses.catalog.products.edit', [$business, $product]);
    }

    public function promotionsIndex(Business $business): Response
    {
        $this->ensurePlatformOperated($business);

        $promotions = Promotion::query()
            ->whereIn('branch_id', $business->branches()->select('id'))
            ->with(['branch:id,name', 'items'])
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Promotion $promotion): array => CatalogData::promotion($promotion));

        return Inertia::render('admin/businesses/catalog/promotions/index', [
            'business' => $this->businessPayload($business),
            'promotions' => $promotions,
            'options' => CatalogData::formOptions($business),
        ]);
    }

    public function promotionsCreate(Business $business): Response
    {
        $this->ensurePlatformOperated($business);

        return Inertia::render('admin/businesses/catalog/promotions/create', [
            'business' => $this->businessPayload($business),
            'options' => CatalogData::formOptions($business),
        ]);
    }

    public function promotionsStore(Request $request, Business $business, CreatePromotion $action): RedirectResponse
    {
        $this->ensurePlatformOperated($business);
        $data = $this->validatePromotion($request, $business);
        $branch = $this->branch($business, $data['branch_id']);
        $promotion = $action->handle($branch, $data, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Promoción creada.']);

        return to_route('admin.businesses.catalog.promotions.edit', [$business, $promotion]);
    }

    public function promotionsEdit(Business $business, Promotion $promotion): Response
    {
        $this->ensurePlatformOperated($business);
        $this->ensurePromotion($business, $promotion);

        return Inertia::render('admin/businesses/catalog/promotions/edit', [
            'business' => $this->businessPayload($business),
            'promotion' => CatalogData::promotion($promotion),
            'options' => CatalogData::formOptions($business),
        ]);
    }

    public function promotionsUpdate(
        Request $request,
        Business $business,
        Promotion $promotion,
        UpdatePromotion $action,
    ): RedirectResponse {
        $this->ensurePlatformOperated($business);
        $this->ensurePromotion($business, $promotion);
        $data = $this->validatePromotion($request, $business, updating: true);
        $action->handle($promotion, $data, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Promoción actualizada.']);

        return to_route('admin.businesses.catalog.promotions.edit', [$business, $promotion]);
    }

    private function ensurePlatformOperated(Business $business): void
    {
        abort_unless(
            $business->operation_mode === BusinessOperationMode::PlatformOperated,
            403,
            'El catálogo Admin aplica a empresas operadas por RIDE.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function businessPayload(Business $business): array
    {
        return [
            'id' => $business->id,
            'name' => $business->name,
            'slug' => $business->slug,
        ];
    }

    private function branch(Business $business, int $branchId): BusinessBranch
    {
        return BusinessBranch::query()
            ->where('business_id', $business->id)
            ->whereKey($branchId)
            ->firstOrFail();
    }

    private function ensureProduct(Business $business, Product $product): void
    {
        abort_unless($business->branches()->whereKey($product->branch_id)->exists(), 404);
    }

    private function ensurePromotion(Business $business, Promotion $promotion): void
    {
        abort_unless($business->branches()->whereKey($promotion->branch_id)->exists(), 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateProduct(Request $request, Business $business, bool $updating = false): array
    {
        if (is_string($request->input('option_groups'))) {
            $decoded = json_decode($request->input('option_groups'), true);
            $request->merge(['option_groups' => is_array($decoded) ? $decoded : []]);
        }

        $branchRule = $updating
            ? ['sometimes']
            : [
                'required',
                'integer',
                Rule::exists('business_branches', 'id')
                    ->where('business_id', $business->id)
                    ->whereNull('deleted_at'),
            ];

        return $request->validate([
            'branch_id' => $branchRule,
            'product_category_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'list_price' => ['required', 'numeric', 'min:0'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            'is_available' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'allow_special_instructions' => ['sometimes', 'boolean'],
            'option_groups' => ['nullable', 'array'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePromotion(Request $request, Business $business, bool $updating = false): array
    {
        if (is_string($request->input('items'))) {
            $decoded = json_decode($request->input('items'), true);
            $request->merge(['items' => is_array($decoded) ? $decoded : []]);
        }

        $branchRule = $updating
            ? ['sometimes']
            : [
                'required',
                'integer',
                Rule::exists('business_branches', 'id')
                    ->where('business_id', $business->id)
                    ->whereNull('deleted_at'),
            ];

        return $request->validate([
            'branch_id' => $branchRule,
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'promotion_price' => ['required', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(PromotionStatus::class)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.is_external_item' => ['required', 'boolean'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.name' => ['nullable', 'string', 'max:150'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.01'],
            'items.*.original_price' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}
