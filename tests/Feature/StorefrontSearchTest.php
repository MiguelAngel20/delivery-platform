<?php

use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\Product;

test('search page includes active restaurants from the catalog', function () {
    $business = Business::factory()->create([
        'name' => 'Tacos El Norte',
        'slug' => 'tacos-el-norte',
        'status' => BusinessStatus::Active,
        'business_type' => 'Tacos',
    ]);
    BusinessBranch::factory()->for($business)->create();

    $this->get(route('search', ['q' => 'tacos']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/search/index')
            ->where('q', 'tacos')
            ->has('restaurants', 1)
            ->where('restaurants.0.slug', 'tacos-el-norte')
            ->has('products')
            ->has('promotions')
            ->has('categories'));
});

test('search page includes products linked to restaurant slugs', function () {
    $business = Business::factory()->create([
        'name' => 'Burger House',
        'slug' => 'burger-house',
        'status' => BusinessStatus::Active,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();
    Product::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Hamburguesa clásica',
        'is_active' => true,
    ]);

    $this->get(route('search', ['q' => 'hamburguesa']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/search/index')
            ->has('products', 1)
            ->where('products.0.restaurantSlug', 'burger-house')
            ->where('products.0.name', 'Hamburguesa clásica'));
});

test('restaurant from search catalog can be opened by slug', function () {
    $business = Business::factory()->create([
        'name' => 'Pizza Roma',
        'slug' => 'pizza-roma-real',
        'status' => BusinessStatus::Active,
    ]);
    BusinessBranch::factory()->for($business)->create();

    $this->get(route('restaurants.show', ['slug' => $business->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/restaurants/show')
            ->where('restaurant.slug', $business->slug));
});
