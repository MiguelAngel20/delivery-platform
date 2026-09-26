<?php

namespace App\Http\Controllers\Web\Admin\Catalog;

use App\Actions\Catalog\CreateProduct;
use App\Actions\Catalog\CreatePromotion;
use App\Actions\Catalog\UpdateProduct;
use App\Actions\Catalog\UpdatePromotion;
use App\Enums\BusinessOperationMode;
use App\Enums\ProductOptionGroupType;
use App\Enums\PromotionStatus;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Promotion;
use App\Support\Catalog\CatalogListPagination;
use App\Support\CatalogData;
use App\Support\CategorySchedule;
use App\Support\ProductImageStorage;
use App\Support\PromotionSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

        $perPage = CatalogListPagination::perPage($request);

        $categories = ProductCategory::query()
            ->whereIn('branch_id', $business->branches()->select('id'))
            ->roots()
            ->with(['branch:id,name'])
            ->withCount(['products', 'children'])
            ->orderBy('sort_order')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ProductCategory $category): array => CatalogData::category($category));

        return Inertia::render('admin/businesses/catalog/categories/index', [
            'business' => $this->businessPayload($business),
            'categories' => $categories,
            'filters' => [
                'per_page' => $perPage,
            ],
            'options' => CatalogData::formOptions($business),
        ]);
    }

    public function categoriesStore(Request $request, Business $business): RedirectResponse
    {
        $this->ensurePlatformOperated($business);

        $validated = $this->validatePrincipalCategory($request, $business);

        ProductCategory::query()->create([
            ...collect($validated)->except(['has_schedule', 'schedule_hours'])->all(),
            'parent_id' => null,
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            ...CategorySchedule::attributesFromValidated($validated, allowSchedule: true),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Categoría creada.']);

        return to_route('admin.businesses.catalog.categories.index', $business);
    }

    public function categoriesEdit(Business $business, ProductCategory $category): Response
    {
        $this->ensurePlatformOperated($business);
        $this->ensureCategory($business, $category);
        abort_unless($category->isRoot(), 404);

        $category->load(['branch:id,name']);

        return Inertia::render('admin/businesses/catalog/categories/edit', [
            'business' => $this->businessPayload($business),
            'category' => CatalogData::category($category),
            'options' => CatalogData::formOptions($business),
        ]);
    }

    public function categoriesUpdate(Request $request, Business $business, ProductCategory $category): RedirectResponse
    {
        $this->ensurePlatformOperated($business);
        $this->ensureCategory($business, $category);
        abort_unless($category->isRoot(), 404);

        $validated = $this->validatePrincipalCategory($request, $business, updating: true);

        $category->update([
            ...collect($validated)->except(['branch_id', 'has_schedule', 'schedule_hours'])->all(),
            'parent_id' => null,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) ($validated['sort_order'] ?? $category->sort_order),
            ...CategorySchedule::attributesFromValidated($validated, allowSchedule: true),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Categoría actualizada.']);

        return to_route('admin.businesses.catalog.categories.index', $business);
    }

    public function categoriesDestroy(Business $business, ProductCategory $category): RedirectResponse
    {
        $this->ensurePlatformOperated($business);
        $this->ensureCategory($business, $category);
        abort_unless($category->isRoot(), 404);

        if ($category->isInUse()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'No se puede eliminar: la categoría tiene productos o subcategorías asociadas.',
            ]);

            return back();
        }

        $category->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Categoría eliminada.']);

        return back();
    }

    public function subcategoriesIndex(Request $request, Business $business): Response
    {
        $this->ensurePlatformOperated($business);

        $perPage = CatalogListPagination::perPage($request);

        $subcategories = ProductCategory::query()
            ->whereIn('branch_id', $business->branches()->select('id'))
            ->whereNotNull('parent_id')
            ->with(['branch:id,name', 'parent:id,name'])
            ->withCount('products')
            ->orderBy('sort_order')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ProductCategory $category): array => CatalogData::category($category));

        return Inertia::render('admin/businesses/catalog/subcategories/index', [
            'business' => $this->businessPayload($business),
            'subcategories' => $subcategories,
            'filters' => [
                'per_page' => $perPage,
            ],
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
            ...collect($validated)->except(['has_schedule', 'schedule_hours'])->all(),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            ...CategorySchedule::attributesFromValidated([], allowSchedule: false),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Subcategoría creada.']);

        return to_route('admin.businesses.catalog.subcategories.index', $business);
    }

    public function subcategoriesEdit(Business $business, ProductCategory $subcategory): Response
    {
        $this->ensurePlatformOperated($business);
        $this->ensureCategory($business, $subcategory);
        abort_unless($subcategory->isSubcategory(), 404);

        $subcategory->load(['branch:id,name', 'parent:id,name']);

        return Inertia::render('admin/businesses/catalog/subcategories/edit', [
            'business' => $this->businessPayload($business),
            'subcategory' => CatalogData::category($subcategory),
            'options' => CatalogData::formOptions($business),
        ]);
    }

    public function subcategoriesUpdate(Request $request, Business $business, ProductCategory $subcategory): RedirectResponse
    {
        $this->ensurePlatformOperated($business);
        $this->ensureCategory($business, $subcategory);
        abort_unless($subcategory->isSubcategory(), 404);

        $validated = $request->validate([
            'parent_id' => [
                'required',
                'integer',
                Rule::exists('product_categories', 'id')
                    ->where('branch_id', $subcategory->branch_id)
                    ->whereNull('parent_id')
                    ->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $subcategory->update([
            ...$validated,
            'parent_id' => (int) $validated['parent_id'],
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) ($validated['sort_order'] ?? $subcategory->sort_order),
            ...CategorySchedule::attributesFromValidated([], allowSchedule: false),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Subcategoría actualizada.']);

        return to_route('admin.businesses.catalog.subcategories.index', $business);
    }

    public function subcategoriesDestroy(Business $business, ProductCategory $subcategory): RedirectResponse
    {
        $this->ensurePlatformOperated($business);
        $this->ensureCategory($business, $subcategory);
        abort_unless($subcategory->isSubcategory(), 404);

        if ($subcategory->isInUse()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'No se puede eliminar: la subcategoría tiene productos asociados.',
            ]);

            return back();
        }

        $subcategory->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Subcategoría eliminada.']);

        return back();
    }

    public function productsIndex(Request $request, Business $business): Response
    {
        $this->ensurePlatformOperated($business);

        $perPage = CatalogListPagination::perPage($request);
        $categoryId = $request->input('category_id', '');
        $subcategoryId = $request->input('subcategory_id', '');

        $productsQuery = Product::query()
            ->whereIn('branch_id', $business->branches()->select('id'))
            ->with(['category:id,name,parent_id', 'currentPrice', 'branch:id,name']);

        $products = CatalogListPagination::applyProductCategoryFilters(
            $productsQuery,
            $categoryId,
            $subcategoryId,
        )
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Product $product): array => CatalogData::productListRow($product));

        return Inertia::render('admin/businesses/catalog/products/index', [
            'business' => $this->businessPayload($business),
            'products' => $products,
            'filters' => [
                'category_id' => $categoryId,
                'subcategory_id' => $subcategoryId,
                'per_page' => $perPage,
            ],
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

        return to_route('admin.businesses.catalog.products.index', $business);
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
            'El catálogo Admin aplica a empresas operadas por ChisDrive.',
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

    private function ensureCategory(Business $business, ProductCategory $category): void
    {
        abort_unless($business->branches()->whereKey($category->branch_id)->exists(), 404);
    }

    private function ensurePromotion(Business $business, Promotion $promotion): void
    {
        abort_unless($business->branches()->whereKey($promotion->branch_id)->exists(), 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePrincipalCategory(Request $request, Business $business, bool $updating = false): array
    {
        if ($request->has('schedule_hours')) {
            $request->merge([
                'schedule_hours' => CategorySchedule::prepareInput($request->input('schedule_hours')),
            ]);
        }

        $request->merge([
            'has_schedule' => filter_var($request->input('has_schedule'), FILTER_VALIDATE_BOOLEAN),
        ]);

        $hasSchedule = (bool) $request->input('has_schedule');

        $branchRule = $updating
            ? ['sometimes']
            : [
                'required',
                'integer',
                Rule::exists('business_branches', 'id')
                    ->where('business_id', $business->id)
                    ->whereNull('deleted_at'),
            ];

        $rules = [
            'branch_id' => $branchRule,
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'has_schedule' => ['sometimes', 'boolean'],
        ];

        if ($hasSchedule) {
            $rules = [...$rules, ...CategorySchedule::validationRules(required: true)];
        } else {
            $rules['schedule_hours'] = ['nullable'];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($hasSchedule) {
            foreach (CategorySchedule::afterValidation() as $callback) {
                $validator->after($callback);
            }
        }

        return $validator->validate();
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

        $sanitizedGroups = $this->sanitizeAdminOptionGroups($request->input('option_groups'));
        $request->merge(['option_groups' => $sanitizedGroups]);

        if ($request->input('existing_image_path') === '') {
            $request->merge(['existing_image_path' => null]);
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

        $data = $request->validate([
            'branch_id' => $branchRule,
            'product_category_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'existing_image_path' => ['nullable', 'string', 'max:255'],
            'list_price' => ['required', 'numeric', 'min:0'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            'is_available' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'allow_special_instructions' => ['sometimes', 'boolean'],
            'option_groups' => ['nullable', 'array'],
            'option_groups.*.name' => ['required_with:option_groups', 'string', 'max:100'],
            'option_groups.*.type' => ['required_with:option_groups', Rule::enum(ProductOptionGroupType::class)],
            'option_groups.*.is_required' => ['sometimes', 'boolean'],
            'option_groups.*.min_selection' => ['required_with:option_groups', 'integer', 'min:0'],
            'option_groups.*.max_selection' => ['required_with:option_groups', 'integer', 'gte:option_groups.*.min_selection'],
            'option_groups.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'option_groups.*.is_active' => ['sometimes', 'boolean'],
            'option_groups.*.has_option_clusters' => ['sometimes', 'boolean'],
            'option_groups.*.clusters' => ['nullable', 'array'],
            'option_groups.*.clusters.*.name' => ['required_with:option_groups.*.clusters', 'string', 'max:100'],
            'option_groups.*.clusters.*.options' => ['required_with:option_groups.*.clusters', 'array', 'min:1'],
            'option_groups.*.clusters.*.options.*.name' => ['required', 'string', 'max:100'],
            'option_groups.*.clusters.*.options.*.description' => ['nullable', 'string'],
            'option_groups.*.clusters.*.options.*.price_modifier' => ['nullable', 'numeric'],
            'option_groups.*.clusters.*.options.*.is_default' => ['sometimes', 'boolean'],
            'option_groups.*.clusters.*.options.*.is_available' => ['sometimes', 'boolean'],
            'option_groups.*.options' => ['required', 'array', 'min:1'],
            'option_groups.*.options.*.name' => ['required', 'string', 'max:100'],
            'option_groups.*.options.*.description' => ['nullable', 'string'],
            'option_groups.*.options.*.price_modifier' => ['nullable', 'numeric'],
            'option_groups.*.options.*.is_default' => ['sometimes', 'boolean'],
            'option_groups.*.options.*.is_available' => ['sometimes', 'boolean'],
        ]);

        // Keep the fully sanitized payload (Laravel only returns keys present in the rules tree).
        $data['option_groups'] = $sanitizedGroups;

        $existingPath = $data['existing_image_path'] ?? null;

        if (
            filled($existingPath)
            && ! ($data['image'] ?? null) instanceof UploadedFile
            && ! app(ProductImageStorage::class)->isReusablePathForBusiness($business, (string) $existingPath)
        ) {
            throw ValidationException::withMessages([
                'existing_image_path' => 'La imagen seleccionada no pertenece a este negocio.',
            ]);
        }

        return $data;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sanitizeAdminOptionGroups(mixed $groups): array
    {
        if (! is_array($groups)) {
            return [];
        }

        return collect($groups)
            ->filter(fn (mixed $group): bool => is_array($group))
            ->map(function (array $group): array {
                $type = ProductOptionGroupType::tryFrom((string) ($group['type'] ?? ''));
                $hasClusters = $type === ProductOptionGroupType::Choice
                    && filter_var($group['has_option_clusters'] ?? false, FILTER_VALIDATE_BOOLEAN);

                $group['has_option_clusters'] = $hasClusters;

                if ($hasClusters) {
                    $clusters = collect($group['clusters'] ?? [])
                        ->filter(fn (mixed $cluster): bool => is_array($cluster))
                        ->map(function (array $cluster): array {
                            $options = collect($cluster['options'] ?? [])
                                ->filter(fn (mixed $option): bool => is_array($option))
                                ->filter(fn (array $option): bool => filled(trim((string) ($option['name'] ?? ''))))
                                ->values()
                                ->all();

                            return [
                                ...$cluster,
                                'name' => trim((string) ($cluster['name'] ?? '')),
                                'options' => $options,
                            ];
                        })
                        ->filter(fn (array $cluster): bool => $cluster['name'] !== '' && $cluster['options'] !== [])
                        ->values()
                        ->all();

                    $group['clusters'] = $clusters;
                    $group['options'] = collect($clusters)
                        ->flatMap(fn (array $cluster): array => $cluster['options'])
                        ->values()
                        ->all();

                    return $group;
                }

                $group['clusters'] = [];
                $group['options'] = collect($group['options'] ?? [])
                    ->filter(fn (mixed $option): bool => is_array($option))
                    ->filter(fn (array $option): bool => filled(trim((string) ($option['name'] ?? ''))))
                    ->values()
                    ->all();

                return $group;
            })
            ->values()
            ->all();
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

        if (is_string($request->input('recurring_hours'))) {
            $request->merge([
                'recurring_hours' => PromotionSchedule::prepareInput($request->input('recurring_hours')),
            ]);
        } elseif ($request->has('recurring_hours')) {
            $request->merge([
                'recurring_hours' => PromotionSchedule::prepareInput($request->input('recurring_hours')),
            ]);
        }

        $request->merge([
            'is_recurring' => filter_var($request->input('is_recurring'), FILTER_VALIDATE_BOOLEAN),
        ]);

        $isRecurring = (bool) $request->input('is_recurring');

        $branchRule = $updating
            ? ['sometimes']
            : [
                'required',
                'integer',
                Rule::exists('business_branches', 'id')
                    ->where('business_id', $business->id)
                    ->whereNull('deleted_at'),
            ];

        $rules = [
            'branch_id' => $branchRule,
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'promotion_price' => ['required', 'numeric', 'min:0'],
            'is_recurring' => ['required', 'boolean'],
            'status' => ['required', Rule::enum(PromotionStatus::class)],
            'items' => ['nullable', 'array'],
            'items.*.is_external_item' => ['required_with:items', 'boolean'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.name' => ['nullable', 'string', 'max:150'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.01'],
            'items.*.original_price' => ['nullable', 'numeric', 'min:0'],
        ];

        if ($isRecurring) {
            $rules['starts_at'] = ['nullable'];
            $rules['ends_at'] = ['nullable'];
            $rules['recurrence_starts_on'] = ['required', 'date'];
            $rules['recurrence_ends_on'] = ['nullable', 'date', 'after_or_equal:recurrence_starts_on'];
            $rules = [...$rules, ...PromotionSchedule::validationRules(required: true)];
        } else {
            $rules['starts_at'] = ['nullable', 'date'];
            $rules['ends_at'] = ['nullable', 'date', 'after_or_equal:starts_at'];
            $rules['recurrence_starts_on'] = ['nullable'];
            $rules['recurrence_ends_on'] = ['nullable'];
            $rules['recurring_hours'] = ['nullable'];
        }

        $validator = Validator::make($request->all(), $rules);

        foreach (PromotionSchedule::afterValidation() as $callback) {
            $validator->after($callback);
        }

        return $validator->validate();
    }
}
