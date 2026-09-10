<?php

use App\Actions\Catalog\ChangeProductPrice;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Enums\ProductOptionGroupType;
use App\Enums\PromotionStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\BusinessUser;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use App\Models\ProductPrice;
use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

function seedCatalogBusinessAdmin(): array
{
    $admin = User::factory()->businessAdmin()->create();
    $business = Business::factory()->create([
        'operation_mode' => BusinessOperationMode::Partner,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();
    $otherBusiness = Business::factory()->create();
    $otherBranch = BusinessBranch::factory()->for($otherBusiness)->create();

    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ])->branches()->sync([$branch->id]);

    return compact('admin', 'business', 'branch', 'otherBranch');
}

test('business admin receives validation errors when creating product without required fields', function () {
    ['admin' => $admin] = seedCatalogBusinessAdmin();

    $this->actingAs($admin)
        ->from(route('business.products.create'))
        ->post(route('business.products.store'), [])
        ->assertSessionHasErrors(['branch_id', 'name', 'list_price'])
        ->assertRedirect(route('business.products.create'));
});

test('business admin receives validation errors when creating category without required fields', function () {
    ['admin' => $admin] = seedCatalogBusinessAdmin();

    $this->actingAs($admin)
        ->from(route('business.categories.create'))
        ->post(route('business.categories.store'), [])
        ->assertSessionHasErrors(['branch_id', 'name'])
        ->assertRedirect(route('business.categories.create'));
});

test('business admin receives validation errors when creating promotion without required fields', function () {
    ['admin' => $admin] = seedCatalogBusinessAdmin();

    $this->actingAs($admin)
        ->from(route('business.promotions.create'))
        ->post(route('business.promotions.store'), [])
        ->assertSessionHasErrors(['branch_id', 'name', 'promotion_price', 'status', 'items'])
        ->assertRedirect(route('business.promotions.create'));
});

test('business admin can create category', function () {
    ['admin' => $admin, 'branch' => $branch] = seedCatalogBusinessAdmin();

    $this->actingAs($admin)
        ->post(route('business.categories.store'), [
            'branch_id' => $branch->id,
            'name' => 'Hamburguesas',
            'description' => 'Clásicas',
            'sort_order' => 1,
            'is_active' => true,
        ])
        ->assertRedirect(route('business.categories.index'));

    expect(ProductCategory::query()->where('name', 'Hamburguesas')->exists())->toBeTrue();
});

test('business admin can create product', function () {
    ['admin' => $admin, 'branch' => $branch] = seedCatalogBusinessAdmin();

    $category = ProductCategory::factory()->create(['branch_id' => $branch->id]);

    $response = $this->actingAs($admin)
        ->post(route('business.products.store'), [
            'branch_id' => $branch->id,
            'product_category_id' => $category->id,
            'name' => 'Hamburguesa clásica',
            'description' => 'Demo',
            'list_price' => 105,
            'is_available' => true,
            'is_active' => true,
            'allow_special_instructions' => true,
            'option_groups' => [
                [
                    'name' => 'Ingredientes',
                    'type' => ProductOptionGroupType::Removable->value,
                    'is_required' => false,
                    'min_selection' => 0,
                    'max_selection' => 10,
                    'options' => [
                        ['name' => 'Lechuga', 'price_modifier' => 0, 'is_default' => true],
                        ['name' => 'Tomate', 'price_modifier' => 0, 'is_default' => true],
                    ],
                ],
            ],
        ]);

    $product = Product::query()->where('name', 'Hamburguesa clásica')->first();

    expect($product)->not->toBeNull()
        ->and($product?->branch_id)->toBe($branch->id)
        ->and($product?->currentPrice?->list_price)->toBe('105.00');

    $response->assertRedirect(route('business.products.edit', $product));
});

test('product belongs to branch', function () {
    $branch = BusinessBranch::factory()->create();
    $product = Product::factory()->create(['branch_id' => $branch->id]);

    expect($product->branch->is($branch))->toBeTrue();
});

test('business admin cannot create product in another business branch', function () {
    ['admin' => $admin, 'otherBranch' => $otherBranch] = seedCatalogBusinessAdmin();

    $this->actingAs($admin)
        ->post(route('business.products.store'), [
            'branch_id' => $otherBranch->id,
            'name' => 'Producto ajeno',
            'list_price' => 50,
        ])
        ->assertForbidden();

    expect(Product::query()->where('name', 'Producto ajeno')->exists())->toBeFalse();
});

test('product can have removable options', function () {
    $product = Product::factory()->create();
    $group = ProductOptionGroup::factory()->removable()->create([
        'product_id' => $product->id,
        'name' => 'Ingredientes',
    ]);
    ProductOption::factory()->create([
        'option_group_id' => $group->id,
        'name' => 'Cebolla',
        'is_default' => true,
    ]);

    expect($product->optionGroups()->first()?->type)->toBe(ProductOptionGroupType::Removable)
        ->and($group->options()->where('name', 'Cebolla')->exists())->toBeTrue();
});

test('product can have addon options', function () {
    $product = Product::factory()->create();
    $group = ProductOptionGroup::factory()->addon()->create(['product_id' => $product->id]);
    ProductOption::factory()->create([
        'option_group_id' => $group->id,
        'name' => 'Queso extra',
        'price_modifier' => 15,
    ]);

    expect($group->fresh()->type)->toBe(ProductOptionGroupType::Addon)
        ->and((float) $group->options()->first()?->price_modifier)->toBe(15.0);
});

test('product can have required choice group', function () {
    $product = Product::factory()->create();
    $group = ProductOptionGroup::factory()->choice()->create([
        'product_id' => $product->id,
        'name' => 'Salsa',
    ]);

    expect($group->is_required)->toBeTrue()
        ->and($group->min_selection)->toBe(1)
        ->and($group->max_selection)->toBe(1);
});

test('product has only one active price', function () {
    $product = Product::factory()->create();
    $actor = User::factory()->systemAdmin()->create();

    app(ChangeProductPrice::class)->handle($product, '105.00', $actor);
    app(ChangeProductPrice::class)->handle($product, '110.00', $actor);

    expect(ProductPrice::query()->where('product_id', $product->id)->where('is_active', true)->count())->toBe(1)
        ->and((string) $product->fresh()->currentPrice?->list_price)->toBe('110.00');
});

test('changing price preserves history', function () {
    $product = Product::factory()->create();
    $actor = User::factory()->systemAdmin()->create();

    app(ChangeProductPrice::class)->handle($product, '105.00', $actor);
    app(ChangeProductPrice::class)->handle($product, '110.00', $actor);

    expect(ProductPrice::query()->where('product_id', $product->id)->count())->toBe(2)
        ->and(ProductPrice::query()->where('product_id', $product->id)->where('is_active', false)->count())->toBe(1);
});

test('promotion can contain menu product', function () {
    ['admin' => $admin, 'branch' => $branch] = seedCatalogBusinessAdmin();
    $product = Product::factory()->create(['branch_id' => $branch->id]);
    ProductPrice::factory()->create(['product_id' => $product->id, 'list_price' => 105]);

    $this->actingAs($admin)
        ->post(route('business.promotions.store'), [
            'branch_id' => $branch->id,
            'name' => 'Combo menú',
            'promotion_price' => 120,
            'status' => PromotionStatus::Active->value,
            'items' => [
                [
                    'is_external_item' => false,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'quantity' => 1,
                ],
            ],
        ])
        ->assertRedirect();

    $promotion = Promotion::query()->where('name', 'Combo menú')->first();

    expect($promotion)->not->toBeNull()
        ->and($promotion?->items()->where('product_id', $product->id)->exists())->toBeTrue();
});

test('promotion can contain external item', function () {
    ['admin' => $admin, 'branch' => $branch] = seedCatalogBusinessAdmin();

    $this->actingAs($admin)
        ->post(route('business.promotions.store'), [
            'branch_id' => $branch->id,
            'name' => 'Combo híbrido',
            'promotion_price' => 120,
            'status' => PromotionStatus::Active->value,
            'items' => [
                [
                    'is_external_item' => true,
                    'product_id' => null,
                    'name' => 'Jugo',
                    'quantity' => 1,
                ],
            ],
        ])
        ->assertRedirect();

    $item = PromotionItem::query()
        ->where('name', 'Jugo')
        ->where('is_external_item', true)
        ->first();

    expect($item)->not->toBeNull()
        ->and($item?->product_id)->toBeNull();
});

test('external item does not require product_id', function () {
    $promotion = Promotion::factory()->create();

    $item = PromotionItem::factory()->create([
        'promotion_id' => $promotion->id,
        'product_id' => null,
        'name' => 'Jugo',
        'is_external_item' => true,
    ]);

    expect($item->product_id)->toBeNull()
        ->and($item->is_external_item)->toBeTrue();
});

test('business employee cannot manage catalog', function () {
    $employee = User::factory()->businessEmployee()->create();
    $business = Business::factory()->create();
    $branch = BusinessBranch::factory()->for($business)->create();

    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $employee->id,
        'role' => BusinessUserRole::BusinessEmployee,
        'status' => BusinessUserStatus::Active,
    ]);

    $this->actingAs($employee)
        ->post(route('business.categories.store'), [
            'branch_id' => $branch->id,
            'name' => 'No permitido',
        ])
        ->assertForbidden();
});

test('system admin can manage platform operated catalog', function () {
    $systemAdmin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create([
        'operation_mode' => BusinessOperationMode::PlatformOperated,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();

    $this->actingAs($systemAdmin)
        ->post(route('admin.businesses.catalog.categories.store', $business), [
            'branch_id' => $branch->id,
            'name' => 'Admin categoría',
            'is_active' => true,
        ])
        ->assertRedirect();

    expect(ProductCategory::query()->where('name', 'Admin categoría')->exists())->toBeTrue();
});

test('system admin is redirected to products index after creating a product', function () {
    $systemAdmin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create([
        'operation_mode' => BusinessOperationMode::PlatformOperated,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();
    $category = ProductCategory::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($systemAdmin)
        ->post(route('admin.businesses.catalog.products.store', $business), [
            'branch_id' => $branch->id,
            'product_category_id' => $category->id,
            'name' => 'Producto admin',
            'description' => 'Creado desde admin',
            'list_price' => 80,
            'is_available' => true,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.businesses.catalog.products.index', $business));

    expect(Product::query()->where('name', 'Producto admin')->exists())->toBeTrue();
});

test('business admin can manage platform operated catalog', function () {
    $admin = User::factory()->businessAdmin()->create();
    $business = Business::factory()->create([
        'operation_mode' => BusinessOperationMode::PlatformOperated,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();

    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ])->branches()->sync([$branch->id]);

    $this->actingAs($admin)
        ->post(route('business.categories.store'), [
            'branch_id' => $branch->id,
            'name' => 'Categoría afiliación',
        ])
        ->assertRedirect();

    expect(ProductCategory::query()->where('name', 'Categoría afiliación')->exists())->toBeTrue();
});

test('products can reuse an existing image path from the same business', function () {
    Storage::fake('public');

    $systemAdmin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create([
        'operation_mode' => BusinessOperationMode::PlatformOperated,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();
    $category = ProductCategory::factory()->create(['branch_id' => $branch->id]);

    $sharedPath = 'products/images/shared-tacos.webp';
    Storage::disk('public')->put($sharedPath, 'fake-image');

    Product::factory()->create([
        'branch_id' => $branch->id,
        'product_category_id' => $category->id,
        'name' => 'Taco asada',
        'image_path' => $sharedPath,
    ]);

    $this->actingAs($systemAdmin)
        ->post(route('admin.businesses.catalog.products.store', $business), [
            'branch_id' => $branch->id,
            'product_category_id' => $category->id,
            'name' => 'Taco pastor',
            'list_price' => 45,
            'existing_image_path' => $sharedPath,
            'is_available' => true,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.businesses.catalog.products.index', $business));

    $pastor = Product::query()->where('name', 'Taco pastor')->first();

    expect($pastor)->not->toBeNull()
        ->and($pastor?->image_path)->toBe($sharedPath)
        ->and(Storage::disk('public')->exists($sharedPath))->toBeTrue();
});

test('product cannot reuse an image path from another business', function () {
    Storage::fake('public');

    $systemAdmin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create([
        'operation_mode' => BusinessOperationMode::PlatformOperated,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();
    $category = ProductCategory::factory()->create(['branch_id' => $branch->id]);

    $foreignPath = 'products/images/foreign.webp';
    Storage::disk('public')->put($foreignPath, 'fake-image');

    $otherBusiness = Business::factory()->create();
    $otherBranch = BusinessBranch::factory()->for($otherBusiness)->create();
    Product::factory()->create([
        'branch_id' => $otherBranch->id,
        'image_path' => $foreignPath,
    ]);

    $this->actingAs($systemAdmin)
        ->from(route('admin.businesses.catalog.products.create', $business))
        ->post(route('admin.businesses.catalog.products.store', $business), [
            'branch_id' => $branch->id,
            'product_category_id' => $category->id,
            'name' => 'Producto inválido',
            'list_price' => 30,
            'existing_image_path' => $foreignPath,
        ])
        ->assertSessionHasErrors('existing_image_path');

    expect(Product::query()->where('name', 'Producto inválido')->exists())->toBeFalse();
});

test('replacing a shared product image keeps the file while another product uses it', function () {
    Storage::fake('public');

    $systemAdmin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create([
        'operation_mode' => BusinessOperationMode::PlatformOperated,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();
    $category = ProductCategory::factory()->create(['branch_id' => $branch->id]);

    $sharedPath = 'products/images/keep-shared.webp';
    $nextPath = 'products/images/other.webp';
    Storage::disk('public')->put($sharedPath, 'shared');
    Storage::disk('public')->put($nextPath, 'other');

    $shared = Product::factory()->create([
        'branch_id' => $branch->id,
        'product_category_id' => $category->id,
        'name' => 'Comparte imagen',
        'image_path' => $sharedPath,
    ]);
    Product::factory()->create([
        'branch_id' => $branch->id,
        'product_category_id' => $category->id,
        'name' => 'Ya usa other',
        'image_path' => $nextPath,
    ]);
    $changing = Product::factory()->create([
        'branch_id' => $branch->id,
        'product_category_id' => $category->id,
        'name' => 'Cambia imagen',
        'image_path' => $sharedPath,
    ]);

    $this->actingAs($systemAdmin)
        ->post(route('admin.businesses.catalog.products.update', [$business, $changing]), [
            'product_category_id' => $category->id,
            'name' => 'Cambia imagen',
            'list_price' => 55,
            'existing_image_path' => $nextPath,
            'is_available' => true,
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($changing->fresh()->image_path)->toBe($nextPath)
        ->and($shared->fresh()->image_path)->toBe($sharedPath)
        ->and(Storage::disk('public')->exists($sharedPath))->toBeTrue()
        ->and(Storage::disk('public')->exists($nextPath))->toBeTrue();
});

test('catalog form options expose distinct product images for the business', function () {
    Storage::fake('public');

    $systemAdmin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create([
        'operation_mode' => BusinessOperationMode::PlatformOperated,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();

    $path = 'products/images/menu.webp';
    Storage::disk('public')->put($path, 'menu');

    Product::factory()->count(2)->create([
        'branch_id' => $branch->id,
        'image_path' => $path,
    ]);

    $this->actingAs($systemAdmin)
        ->get(route('admin.businesses.catalog.products.create', $business))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/businesses/catalog/products/create')
            ->has('options.product_images', 1)
            ->where('options.product_images.0.path', $path));
});

test('business admin can create product with optional size group and absolute prices', function () {
    ['admin' => $admin, 'branch' => $branch] = seedCatalogBusinessAdmin();
    $category = ProductCategory::factory()->create(['branch_id' => $branch->id]);

    $response = $this->actingAs($admin)
        ->post(route('business.products.store'), [
            'branch_id' => $branch->id,
            'product_category_id' => $category->id,
            'name' => 'Cóctel de camarón',
            'description' => 'Con salsa especial',
            'list_price' => 999,
            'is_available' => true,
            'is_active' => true,
            'allow_special_instructions' => true,
            'option_groups' => [
                [
                    'name' => 'Tamaños / porciones',
                    'type' => ProductOptionGroupType::Size->value,
                    'is_required' => true,
                    'min_selection' => 1,
                    'max_selection' => 1,
                    'options' => [
                        ['name' => 'Chico', 'price_modifier' => 80, 'is_default' => false],
                        ['name' => 'Media', 'price_modifier' => 120, 'is_default' => false],
                        ['name' => 'Grande', 'price_modifier' => 130, 'is_default' => false],
                    ],
                ],
            ],
        ]);

    $product = Product::query()->where('name', 'Cóctel de camarón')->first();

    expect($product)->not->toBeNull()
        ->and($product?->currentPrice?->list_price)->toBe('80.00');

    $sizeGroup = $product?->optionGroups()->where('type', ProductOptionGroupType::Size)->first();

    expect($sizeGroup)->not->toBeNull()
        ->and($sizeGroup?->is_required)->toBeTrue()
        ->and($sizeGroup?->min_selection)->toBe(1)
        ->and($sizeGroup?->max_selection)->toBe(1);

    $modifiers = $sizeGroup?->options()->orderBy('sort_order')->pluck('price_modifier', 'name');

    expect($modifiers?->get('Chico'))->toBe('0.00')
        ->and($modifiers?->get('Media'))->toBe('40.00')
        ->and($modifiers?->get('Grande'))->toBe('50.00');

    $response->assertRedirect(route('business.products.edit', $product));
});

test('product without sizes keeps manual list price', function () {
    ['admin' => $admin, 'branch' => $branch] = seedCatalogBusinessAdmin();

    $this->actingAs($admin)
        ->post(route('business.products.store'), [
            'branch_id' => $branch->id,
            'name' => 'Refresco',
            'list_price' => 35,
            'is_available' => true,
            'is_active' => true,
        ])
        ->assertRedirect();

    $product = Product::query()->where('name', 'Refresco')->first();

    expect($product?->currentPrice?->list_price)->toBe('35.00')
        ->and($product?->optionGroups()->count())->toBe(0);
});

test('size option without price fails validation on create', function () {
    ['admin' => $admin, 'branch' => $branch] = seedCatalogBusinessAdmin();

    $this->actingAs($admin)
        ->from(route('business.products.create'))
        ->post(route('business.products.store'), [
            'branch_id' => $branch->id,
            'name' => 'Producto con tamaño inválido',
            'list_price' => 50,
            'is_available' => true,
            'is_active' => true,
            'option_groups' => [
                [
                    'name' => 'Tamaños / porciones',
                    'type' => ProductOptionGroupType::Size->value,
                    'is_required' => true,
                    'min_selection' => 1,
                    'max_selection' => 1,
                    'options' => [
                        ['name' => 'Chico', 'price_modifier' => '', 'is_default' => false],
                    ],
                ],
            ],
        ])
        ->assertSessionHasErrors()
        ->assertRedirect(route('business.products.create'));

    expect(Product::query()->where('name', 'Producto con tamaño inválido')->exists())->toBeFalse();
});
