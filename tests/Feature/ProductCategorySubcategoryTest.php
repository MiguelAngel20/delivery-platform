<?php

use App\Enums\BranchStatus;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\BusinessUser;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;

function seedSubcategoryBusinessAdmin(): array
{
    $admin = User::factory()->businessAdmin()->create();
    $business = Business::factory()->create([
        'operation_mode' => BusinessOperationMode::Partner,
        'status' => BusinessStatus::Active,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create([
        'status' => BranchStatus::Active,
    ]);

    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ])->branches()->sync([$branch->id]);

    return compact('admin', 'business', 'branch');
}

test('business admin can create a subcategory under a root category', function () {
    ['admin' => $admin, 'branch' => $branch] = seedSubcategoryBusinessAdmin();

    $parent = ProductCategory::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Homa',
    ]);

    $this->actingAs($admin)
        ->post(route('business.subcategories.store'), [
            'branch_id' => $branch->id,
            'parent_id' => $parent->id,
            'name' => 'Pizza',
            'description' => 'Pizzas de Homa',
            'is_active' => true,
        ])
        ->assertRedirect(route('business.subcategories.index'));

    $child = ProductCategory::query()->where('name', 'Pizza')->first();

    expect($child)->not->toBeNull()
        ->and($child?->parent_id)->toBe($parent->id)
        ->and($child?->branch_id)->toBe($branch->id)
        ->and($child?->displayPath())->toBe('Homa › Pizza');
});

test('categories store always creates a principal category without parent', function () {
    ['admin' => $admin, 'branch' => $branch] = seedSubcategoryBusinessAdmin();

    $parent = ProductCategory::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Homa',
    ]);

    $this->actingAs($admin)
        ->post(route('business.categories.store'), [
            'branch_id' => $branch->id,
            'parent_id' => $parent->id,
            'name' => 'Pizza',
            'is_active' => true,
        ])
        ->assertRedirect(route('business.categories.index'));

    $created = ProductCategory::query()->where('name', 'Pizza')->first();

    expect($created?->parent_id)->toBeNull();
});

test('subcategory parent must belong to the same branch and be a root', function () {
    ['admin' => $admin, 'branch' => $branch] = seedSubcategoryBusinessAdmin();

    $parent = ProductCategory::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Homa',
    ]);

    $sub = ProductCategory::factory()->childOf($parent)->create([
        'name' => 'Pizza',
    ]);

    $this->actingAs($admin)
        ->post(route('business.subcategories.store'), [
            'branch_id' => $branch->id,
            'parent_id' => $sub->id,
            'name' => 'Extra level',
            'is_active' => true,
        ])
        ->assertSessionHasErrors(['parent_id']);
});

test('products can be assigned to a subcategory and appear under path on storefront', function () {
    ['branch' => $branch, 'business' => $business] = seedSubcategoryBusinessAdmin();

    $parent = ProductCategory::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Homa',
        'is_active' => true,
    ]);

    $child = ProductCategory::factory()->childOf($parent)->create([
        'name' => 'Pizza',
        'is_active' => true,
    ]);

    $product = Product::factory()->create([
        'branch_id' => $branch->id,
        'product_category_id' => $child->id,
        'name' => 'Margarita',
        'is_active' => true,
        'is_available' => true,
    ]);

    $this->get(route('restaurants.show', $business->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/restaurants/show')
            ->has('categories', 1)
            ->where('categories.0.name', 'Homa')
            ->where('categories.0.children.0.name', 'Pizza')
            ->where('products.0.id', $product->id)
            ->where('products.0.category', 'Homa › Pizza'));
});

test('cannot nest a category that already has children', function () {
    ['admin' => $admin, 'branch' => $branch] = seedSubcategoryBusinessAdmin();

    $rootA = ProductCategory::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Homa',
    ]);
    $rootB = ProductCategory::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Hamo',
    ]);
    ProductCategory::factory()->childOf($rootA)->create(['name' => 'Pizza']);

    $this->actingAs($admin)
        ->put(route('business.categories.update', $rootA), [
            'parent_id' => $rootB->id,
            'name' => 'Homa',
            'is_active' => true,
        ])
        ->assertSessionHasErrors(['parent_id']);
});
