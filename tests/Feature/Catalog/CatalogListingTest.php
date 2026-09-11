<?php

use App\Enums\BusinessOperationMode;
use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\BusinessUser;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Support\Catalog\CatalogListPagination;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;

function seedCatalogListingBusinessAdmin(): array
{
    $admin = User::factory()->businessAdmin()->create();
    $business = Business::factory()->create([
        'operation_mode' => BusinessOperationMode::Partner,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();

    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ])->branches()->sync([$branch->id]);

    return compact('admin', 'business', 'branch');
}

test('catalog list pagination defaults to 25 and accepts allowed per page values', function () {
    expect(CatalogListPagination::perPage(Request::create('/', 'GET', ['per_page' => 50])))
        ->toBe(50)
        ->and(CatalogListPagination::perPage(Request::create('/', 'GET', ['per_page' => 15])))
        ->toBe(CatalogListPagination::DEFAULT_PER_PAGE)
        ->and(CatalogListPagination::perPage(Request::create('/', 'GET')))
        ->toBe(CatalogListPagination::DEFAULT_PER_PAGE);
});

test('business products index paginates with default per page and exposes filters', function () {
    ['admin' => $admin, 'branch' => $branch] = seedCatalogListingBusinessAdmin();

    Product::factory()->count(3)->create(['branch_id' => $branch->id]);

    $this->actingAs($admin)
        ->get(route('business.products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('business/products/index')
            ->has('products.data', 3)
            ->where('filters.per_page', 25)
            ->where('products.per_page', 25));
});

test('business products index respects per_page query', function () {
    ['admin' => $admin, 'branch' => $branch] = seedCatalogListingBusinessAdmin();

    Product::factory()->count(30)->create(['branch_id' => $branch->id]);

    $this->actingAs($admin)
        ->get(route('business.products.index', ['per_page' => 50]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('business/products/index')
            ->has('products.data', 30)
            ->where('filters.per_page', 50)
            ->where('products.per_page', 50));
});

test('business products can filter by principal category including subcategories', function () {
    ['admin' => $admin, 'branch' => $branch] = seedCatalogListingBusinessAdmin();

    $principal = ProductCategory::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Tacos',
    ]);
    $subcategory = ProductCategory::factory()->childOf($principal)->create([
        'name' => 'Asada',
    ]);
    $other = ProductCategory::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Bebidas',
    ]);

    $inPrincipal = Product::factory()->create([
        'branch_id' => $branch->id,
        'product_category_id' => $principal->id,
        'name' => 'Taco directo',
    ]);
    $inSub = Product::factory()->create([
        'branch_id' => $branch->id,
        'product_category_id' => $subcategory->id,
        'name' => 'Taco asada',
    ]);
    Product::factory()->create([
        'branch_id' => $branch->id,
        'product_category_id' => $other->id,
        'name' => 'Refresco',
    ]);

    $this->actingAs($admin)
        ->get(route('business.products.index', ['category_id' => $principal->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('business/products/index')
            ->has('products.data', 2)
            ->where('filters.category_id', (string) $principal->id)
            ->where('products.data', fn ($rows) => collect($rows)->pluck('id')->sort()->values()->all() === [
                $inPrincipal->id,
                $inSub->id,
            ]));
});

test('business products can filter by subcategory exactly', function () {
    ['admin' => $admin, 'branch' => $branch] = seedCatalogListingBusinessAdmin();

    $principal = ProductCategory::factory()->create(['branch_id' => $branch->id]);
    $subcategory = ProductCategory::factory()->childOf($principal)->create();

    Product::factory()->create([
        'branch_id' => $branch->id,
        'product_category_id' => $principal->id,
        'name' => 'En principal',
    ]);
    $match = Product::factory()->create([
        'branch_id' => $branch->id,
        'product_category_id' => $subcategory->id,
        'name' => 'En subcategoría',
    ]);

    $this->actingAs($admin)
        ->get(route('business.products.index', [
            'category_id' => $principal->id,
            'subcategory_id' => $subcategory->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('business/products/index')
            ->has('products.data', 1)
            ->where('products.data.0.id', $match->id));
});

test('business categories and subcategories paginate with selectable per page', function () {
    ['admin' => $admin, 'branch' => $branch] = seedCatalogListingBusinessAdmin();

    $principals = ProductCategory::factory()->count(3)->create(['branch_id' => $branch->id]);
    ProductCategory::factory()->childOf($principals->first())->count(2)->create();

    $this->actingAs($admin)
        ->get(route('business.categories.index', ['per_page' => 25]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('business/categories/index')
            ->has('categories.data', 3)
            ->where('filters.per_page', 25));

    $this->actingAs($admin)
        ->get(route('business.subcategories.index', ['per_page' => 75]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('business/subcategories/index')
            ->has('subcategories.data', 2)
            ->where('filters.per_page', 75));
});

test('admin catalog products support pagination and category filters', function () {
    $systemAdmin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create([
        'operation_mode' => BusinessOperationMode::PlatformOperated,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();
    $category = ProductCategory::factory()->create(['branch_id' => $branch->id]);

    Product::factory()->count(2)->create([
        'branch_id' => $branch->id,
        'product_category_id' => $category->id,
    ]);
    Product::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($systemAdmin)
        ->get(route('admin.businesses.catalog.products.index', [
            'business' => $business,
            'category_id' => $category->id,
            'per_page' => 50,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/businesses/catalog/products/index')
            ->has('products.data', 2)
            ->where('filters.per_page', 50)
            ->where('filters.category_id', (string) $category->id));
});
