<?php

use App\Enums\BusinessStatus;
use App\Enums\PromotionStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Promotion;
use App\Models\User;

test('guests can browse the public storefront', function (string $routeName) {
    $this->get(route($routeName))->assertOk();
})->with([
    'home' => 'home',
    'restaurants' => 'restaurants.index',
    'categories' => 'categories.index',
    'promotions' => 'promotions.index',
    'search' => 'search',
    'cart' => 'cart',
]);

test('public home and restaurants render without requiring a query', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/home')
            ->where('maps.default_place_label', 'Comitán de Domínguez, Chiapas'));

    $this->get(route('restaurants.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('public/restaurants/index'));

    $this->get(route('search'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/search/index')
            ->has('q')
            ->has('restaurants')
            ->has('products')
            ->has('promotions')
            ->has('categories'));
});

test('guests can open a restaurant menu', function () {
    $business = Business::factory()->create([
        'slug' => 'pollo-guero',
        'status' => BusinessStatus::Active,
    ]);
    BusinessBranch::factory()->for($business)->create();

    $this->get(route('restaurants.show', ['slug' => 'pollo-guero']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/restaurants/show')
            ->where('restaurant.slug', 'pollo-guero'));
});

test('promotions index lists real promotions and links to an existing business', function () {
    $business = Business::factory()->create([
        'slug' => 'promo-partner',
        'status' => BusinessStatus::Active,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();
    $promotion = Promotion::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Combo demo',
        'status' => PromotionStatus::Active,
    ]);

    $this->get(route('promotions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/promotions/index')
            ->has('promotions', 1)
            ->where('promotions.0.id', (string) $promotion->id)
            ->where('promotions.0.restaurantSlug', 'promo-partner'));

    $this->get(route('restaurants.show', ['slug' => 'promo-partner']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/restaurants/show')
            ->where('restaurant.slug', 'promo-partner'));
});

test('customer can open customer portal pages', function (string $routeName) {
    $user = User::factory()->customer()->create();

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertOk();
})->with([
    'checkout' => 'customer.checkout',
    'orders' => 'customer.orders.index',
    'addresses' => 'customer.addresses.index',
    'profile' => 'customer.profile.index',
]);

test('customer can logout from the storefront', function () {
    $user = User::factory()->customer()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});

test('customer can open order tracking', function () {
    $user = User::factory()->customer()->create();

    $this->actingAs($user)
        ->get(route('customer.orders.show', ['order' => 'ord-1']))
        ->assertOk();
});

test('driver cannot open customer checkout', function () {
    $user = User::factory()->driver()->create();

    $this->actingAs($user)
        ->get(route('customer.checkout'))
        ->assertForbidden();
});

test('guests can fetch a storefront product for cart editing', function () {
    $business = Business::factory()->create([
        'status' => BusinessStatus::Active,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();
    $product = Product::factory()->create([
        'branch_id' => $branch->id,
        'is_active' => true,
        'is_available' => true,
    ]);
    ProductPrice::factory()->create([
        'product_id' => $product->id,
        'list_price' => 99,
        'is_active' => true,
    ]);

    $this->getJson(route('cart.products.show', $product))
        ->assertOk()
        ->assertJsonPath('product.id', $product->id)
        ->assertJsonPath('branch_id', $branch->id)
        ->assertJsonPath('restaurant.slug', $business->slug);
});

test('guests are redirected from customer pages', function () {
    $this->get(route('customer.orders.index'))
        ->assertRedirect(route('login'));
});
