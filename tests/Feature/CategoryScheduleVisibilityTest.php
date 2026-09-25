<?php

use App\Enums\BusinessStatus;
use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\BusinessUser;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use App\Models\User;
use App\Support\BusinessHours;
use App\Support\CategorySchedule;
use Illuminate\Support\Carbon;

/**
 * @return list<array{day: string, is_open: bool, opens_at: string|null, closes_at: string|null}>
 */
function categoryHoursForDays(array $openDays, string $opensAt = '08:00', string $closesAt = '13:00'): array
{
    return collect(BusinessHours::dayKeys())
        ->map(fn (string $day): array => [
            'day' => $day,
            'is_open' => in_array($day, $openDays, true),
            'opens_at' => in_array($day, $openDays, true) ? $opensAt : null,
            'closes_at' => in_array($day, $openDays, true) ? $closesAt : null,
        ])
        ->values()
        ->all();
}

function seedCategoryScheduleBusiness(): array
{
    $admin = User::factory()->businessAdmin()->create();
    $business = Business::factory()->create([
        'status' => BusinessStatus::Active,
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

test('category without schedule is visible at any hour', function () {
    $tz = (string) config('business.hours_timezone', 'America/Mexico_City');
    Carbon::setTestNow(Carbon::parse('2026-09-25 03:00:00', $tz));

    $category = ProductCategory::factory()->create([
        'has_schedule' => false,
        'schedule_hours' => null,
        'is_active' => true,
    ]);

    expect($category->isVisibleNow())->toBeTrue();

    Carbon::setTestNow();
});

test('scheduled breakfast category is visible inside window and hidden outside', function () {
    $tz = (string) config('business.hours_timezone', 'America/Mexico_City');

    $category = ProductCategory::factory()->create([
        'name' => 'Desayuno',
        'has_schedule' => true,
        'schedule_hours' => categoryHoursForDays(
            BusinessHours::dayKeys(),
            '08:00',
            '13:00',
        ),
        'is_active' => true,
    ]);

    Carbon::setTestNow(Carbon::parse('2026-09-25 10:00:00', $tz));
    expect($category->isVisibleNow())->toBeTrue();

    Carbon::setTestNow(Carbon::parse('2026-09-25 15:00:00', $tz));
    expect($category->isVisibleNow())->toBeFalse();

    Carbon::setTestNow();
});

test('subcategory inherits principal category schedule', function () {
    $tz = (string) config('business.hours_timezone', 'America/Mexico_City');

    $parent = ProductCategory::factory()->create([
        'name' => 'Desayuno',
        'has_schedule' => true,
        'schedule_hours' => categoryHoursForDays(
            BusinessHours::dayKeys(),
            '08:00',
            '13:00',
        ),
        'is_active' => true,
    ]);

    $child = ProductCategory::factory()->childOf($parent)->create([
        'name' => 'Huevos',
        'has_schedule' => false,
        'schedule_hours' => null,
        'is_active' => true,
    ]);

    Carbon::setTestNow(Carbon::parse('2026-09-25 10:00:00', $tz));
    expect($child->isVisibleNow())->toBeTrue();

    Carbon::setTestNow(Carbon::parse('2026-09-25 15:00:00', $tz));
    expect($child->isVisibleNow())->toBeFalse();

    Carbon::setTestNow();
});

test('storefront hides scheduled category and products outside hours', function () {
    $tz = (string) config('business.hours_timezone', 'America/Mexico_City');
    Carbon::setTestNow(Carbon::parse('2026-09-25 15:00:00', $tz));

    ['business' => $business, 'branch' => $branch] = seedCategoryScheduleBusiness();

    $desayuno = ProductCategory::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Desayuno',
        'has_schedule' => true,
        'schedule_hours' => categoryHoursForDays(BusinessHours::dayKeys(), '08:00', '13:00'),
        'is_active' => true,
    ]);

    $comida = ProductCategory::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Comida',
        'has_schedule' => true,
        'schedule_hours' => categoryHoursForDays(BusinessHours::dayKeys(), '13:00', '23:00'),
        'is_active' => true,
    ]);

    $always = ProductCategory::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Bebidas',
        'has_schedule' => false,
        'is_active' => true,
    ]);

    $breakfastProduct = Product::factory()->forCategory($desayuno)->create([
        'name' => 'Huevos',
        'is_active' => true,
        'is_available' => true,
    ]);
    $lunchProduct = Product::factory()->forCategory($comida)->create([
        'name' => 'Tacos',
        'is_active' => true,
        'is_available' => true,
    ]);
    $drinkProduct = Product::factory()->forCategory($always)->create([
        'name' => 'Agua',
        'is_active' => true,
        'is_available' => true,
    ]);

    $this->get(route('restaurants.show', $business->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/restaurants/show')
            ->has('categories', 2)
            ->where(
                'categories',
                fn ($categories): bool => collect($categories)
                    ->pluck('name')
                    ->sort()
                    ->values()
                    ->all() === ['Bebidas', 'Comida'],
            )
            ->where(
                'products',
                fn ($products): bool => collect($products)
                    ->pluck('id')
                    ->sort()
                    ->values()
                    ->all() === collect([$lunchProduct->id, $drinkProduct->id])->sort()->values()->all(),
            ));

    expect($breakfastProduct->fresh()->category?->isVisibleNow())->toBeFalse();

    Carbon::setTestNow();
});

test('cart product endpoint rejects products outside category schedule', function () {
    $tz = (string) config('business.hours_timezone', 'America/Mexico_City');
    Carbon::setTestNow(Carbon::parse('2026-09-25 15:00:00', $tz));

    $business = Business::factory()->create(['status' => BusinessStatus::Active]);
    $branch = BusinessBranch::factory()->for($business)->create();
    $category = ProductCategory::factory()->create([
        'branch_id' => $branch->id,
        'has_schedule' => true,
        'schedule_hours' => categoryHoursForDays(BusinessHours::dayKeys(), '08:00', '13:00'),
        'is_active' => true,
    ]);
    $product = Product::factory()->forCategory($category)->create([
        'is_active' => true,
        'is_available' => true,
    ]);
    ProductPrice::factory()->create([
        'product_id' => $product->id,
        'list_price' => 50,
        'is_active' => true,
    ]);

    $this->getJson(route('cart.products.show', $product))->assertNotFound();

    Carbon::setTestNow(Carbon::parse('2026-09-25 10:00:00', $tz));

    $this->getJson(route('cart.products.show', $product))->assertOk();

    Carbon::setTestNow();
});

test('products availability endpoint excludes out-of-schedule category products', function () {
    $tz = (string) config('business.hours_timezone', 'America/Mexico_City');
    Carbon::setTestNow(Carbon::parse('2026-09-25 15:00:00', $tz));

    $business = Business::factory()->create(['status' => BusinessStatus::Active]);
    $branch = BusinessBranch::factory()->for($business)->create();

    $scheduled = ProductCategory::factory()->create([
        'branch_id' => $branch->id,
        'has_schedule' => true,
        'schedule_hours' => categoryHoursForDays(BusinessHours::dayKeys(), '08:00', '13:00'),
        'is_active' => true,
    ]);
    $always = ProductCategory::factory()->create([
        'branch_id' => $branch->id,
        'has_schedule' => false,
        'is_active' => true,
    ]);

    $hidden = Product::factory()->forCategory($scheduled)->create([
        'is_active' => true,
        'is_available' => true,
    ]);
    $visible = Product::factory()->forCategory($always)->create([
        'is_active' => true,
        'is_available' => true,
    ]);

    $this->postJson(route('cart.products.availability'), [
        'ids' => [$hidden->id, $visible->id],
    ])
        ->assertOk()
        ->assertJsonPath('available_ids', [$visible->id]);

    Carbon::setTestNow();
});

test('business admin can save principal category with optional schedule', function () {
    ['admin' => $admin, 'branch' => $branch] = seedCategoryScheduleBusiness();

    $hours = categoryHoursForDays(BusinessHours::dayKeys(), '08:00', '13:00');

    $this->actingAs($admin)
        ->post(route('business.categories.store'), [
            'branch_id' => $branch->id,
            'name' => 'Desayuno',
            'is_active' => true,
            'has_schedule' => true,
            'schedule_hours' => $hours,
        ])
        ->assertRedirect(route('business.categories.index'));

    $category = ProductCategory::query()->where('name', 'Desayuno')->first();

    expect($category)->not->toBeNull()
        ->and($category->has_schedule)->toBeTrue()
        ->and($category->schedule_hours)->not->toBeNull();
});

test('turning off schedule clears schedule_hours', function () {
    ['admin' => $admin, 'branch' => $branch] = seedCategoryScheduleBusiness();

    $category = ProductCategory::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Desayuno',
        'has_schedule' => true,
        'schedule_hours' => categoryHoursForDays(BusinessHours::dayKeys(), '08:00', '13:00'),
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->put(route('business.categories.update', $category), [
            'name' => 'Desayuno',
            'is_active' => true,
            'has_schedule' => false,
        ])
        ->assertRedirect(route('business.categories.index'));

    $category->refresh();

    expect($category->has_schedule)->toBeFalse()
        ->and($category->schedule_hours)->toBeNull();
});

test('has_schedule requires at least one open day', function () {
    ['admin' => $admin, 'branch' => $branch] = seedCategoryScheduleBusiness();

    $this->actingAs($admin)
        ->from(route('business.categories.create'))
        ->post(route('business.categories.store'), [
            'branch_id' => $branch->id,
            'name' => 'Desayuno',
            'is_active' => true,
            'has_schedule' => true,
            'schedule_hours' => CategorySchedule::defaultHours(),
        ])
        ->assertSessionHasErrors(['schedule_hours'])
        ->assertRedirect(route('business.categories.create'));
});

test('subcategory create ignores schedule payload', function () {
    ['admin' => $admin, 'branch' => $branch] = seedCategoryScheduleBusiness();

    $parent = ProductCategory::factory()->create([
        'branch_id' => $branch->id,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->post(route('business.subcategories.store'), [
            'branch_id' => $branch->id,
            'parent_id' => $parent->id,
            'name' => 'Huevos',
            'is_active' => true,
            'has_schedule' => true,
            'schedule_hours' => categoryHoursForDays(BusinessHours::dayKeys(), '08:00', '13:00'),
        ])
        ->assertRedirect(route('business.subcategories.index'));

    $child = ProductCategory::query()->where('name', 'Huevos')->first();

    expect($child)->not->toBeNull()
        ->and($child->has_schedule)->toBeFalse()
        ->and($child->schedule_hours)->toBeNull();
});
