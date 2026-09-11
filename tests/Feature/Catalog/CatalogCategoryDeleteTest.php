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
use Inertia\Testing\AssertableInertia as Assert;

function seedCategoryDeleteBusinessAdmin(): array
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

test('business categories index exposes can_delete flag', function () {
    ['admin' => $admin, 'branch' => $branch] = seedCategoryDeleteBusinessAdmin();

    $free = ProductCategory::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Libre',
    ]);
    $used = ProductCategory::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Usada',
    ]);
    Product::factory()->create([
        'branch_id' => $branch->id,
        'product_category_id' => $used->id,
    ]);

    $this->actingAs($admin)
        ->get(route('business.categories.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('business/categories/index')
            ->has('categories.data', 2)
            ->where(
                'categories.data',
                fn ($rows) => collect($rows)->firstWhere('id', $free->id)['can_delete'] === true
                    && collect($rows)->firstWhere('id', $used->id)['can_delete'] === false,
            ));
});

test('business admin can delete unused category', function () {
    ['admin' => $admin, 'branch' => $branch] = seedCategoryDeleteBusinessAdmin();

    $category = ProductCategory::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($admin)
        ->delete(route('business.categories.destroy', $category))
        ->assertRedirect();

    expect(ProductCategory::query()->whereKey($category->id)->exists())->toBeFalse()
        ->and(ProductCategory::withTrashed()->whereKey($category->id)->exists())->toBeTrue();
});

test('business admin cannot delete category with products', function () {
    ['admin' => $admin, 'branch' => $branch] = seedCategoryDeleteBusinessAdmin();

    $category = ProductCategory::factory()->create(['branch_id' => $branch->id]);
    Product::factory()->create([
        'branch_id' => $branch->id,
        'product_category_id' => $category->id,
    ]);

    $this->actingAs($admin)
        ->from(route('business.categories.index'))
        ->delete(route('business.categories.destroy', $category))
        ->assertRedirect(route('business.categories.index'));

    expect(ProductCategory::query()->whereKey($category->id)->exists())->toBeTrue();
});

test('business admin cannot delete category with subcategories', function () {
    ['admin' => $admin, 'branch' => $branch] = seedCategoryDeleteBusinessAdmin();

    $category = ProductCategory::factory()->create(['branch_id' => $branch->id]);
    ProductCategory::factory()->childOf($category)->create();

    $this->actingAs($admin)
        ->from(route('business.categories.index'))
        ->delete(route('business.categories.destroy', $category))
        ->assertRedirect(route('business.categories.index'));

    expect(ProductCategory::query()->whereKey($category->id)->exists())->toBeTrue();
});

test('business admin can delete unused subcategory but not one with products', function () {
    ['admin' => $admin, 'branch' => $branch] = seedCategoryDeleteBusinessAdmin();

    $principal = ProductCategory::factory()->create(['branch_id' => $branch->id]);
    $free = ProductCategory::factory()->childOf($principal)->create(['name' => 'Libre']);
    $used = ProductCategory::factory()->childOf($principal)->create(['name' => 'Usada']);
    Product::factory()->create([
        'branch_id' => $branch->id,
        'product_category_id' => $used->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('business.subcategories.destroy', $free))
        ->assertRedirect();

    expect(ProductCategory::query()->whereKey($free->id)->exists())->toBeFalse();

    $this->actingAs($admin)
        ->from(route('business.subcategories.index'))
        ->delete(route('business.subcategories.destroy', $used))
        ->assertRedirect(route('business.subcategories.index'));

    expect(ProductCategory::query()->whereKey($used->id)->exists())->toBeTrue();
});

test('admin can delete unused category and cannot delete one in use', function () {
    $systemAdmin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create([
        'operation_mode' => BusinessOperationMode::PlatformOperated,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();

    $free = ProductCategory::factory()->create(['branch_id' => $branch->id]);
    $used = ProductCategory::factory()->create(['branch_id' => $branch->id]);
    Product::factory()->create([
        'branch_id' => $branch->id,
        'product_category_id' => $used->id,
    ]);

    $this->actingAs($systemAdmin)
        ->delete(route('admin.businesses.catalog.categories.destroy', [$business, $free]))
        ->assertRedirect();

    expect(ProductCategory::query()->whereKey($free->id)->exists())->toBeFalse();

    $this->actingAs($systemAdmin)
        ->from(route('admin.businesses.catalog.categories.index', $business))
        ->delete(route('admin.businesses.catalog.categories.destroy', [$business, $used]))
        ->assertRedirect(route('admin.businesses.catalog.categories.index', $business));

    expect(ProductCategory::query()->whereKey($used->id)->exists())->toBeTrue();
});
