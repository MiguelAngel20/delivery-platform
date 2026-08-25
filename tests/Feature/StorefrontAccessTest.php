<?php

use App\Enums\BusinessStatus;
use App\Enums\PromotionStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Promotion;
use App\Models\User;
use App\Support\BusinessHours;

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

test('restaurant page includes location contact and schedule details', function () {
    $business = Business::factory()->create([
        'slug' => 'cafe-plaza',
        'status' => BusinessStatus::Active,
        'phone' => '+50211112222',
        'description' => 'Café de especialidad',
    ]);
    BusinessBranch::factory()->for($business)->create([
        'phone' => '+50233334444',
        'address_text' => '4a Avenida 1-20, Zona 1',
        'latitude' => '16.2514000',
        'longitude' => '-92.1342000',
        'opening_hours' => BusinessHours::defaults(),
    ]);

    $this->get(route('restaurants.show', ['slug' => 'cafe-plaza']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/restaurants/show')
            ->where('restaurant.slug', 'cafe-plaza')
            ->where('restaurant.phone', '+50233334444')
            ->where('restaurant.address', '4a Avenida 1-20, Zona 1')
            ->where('restaurant.description', 'Café de especialidad')
            ->has('restaurant.google_maps_url')
            ->has('restaurant.latitude')
            ->has('restaurant.longitude')
            ->has('restaurant.opening_hours', 7)
            ->where('restaurant.schedule_summary.0.days_label', 'Lunes a Viernes')
            ->where('restaurant.schedule_summary.0.hours_label', '09:00 – 21:00')
            ->where('restaurant.schedule_summary.1.days_label', 'Sábado a Domingo')
            ->where('restaurant.working_days', ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes']));
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
    Customer::factory()->for($user)->create();

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
    $customer = Customer::factory()->for($user)->create();
    $order = Order::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($user)
        ->get(route('customer.orders.show', $order))
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
