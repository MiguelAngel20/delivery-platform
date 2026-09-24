<?php

use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Support\OrderData;

test('item category context resolves subcategory from product relation', function () {
    $root = ProductCategory::factory()->create(['name' => 'Sopas', 'parent_id' => null]);
    $sub = ProductCategory::factory()->childOf($root)->create(['name' => 'Mariscos']);
    $product = Product::factory()->create([
        'branch_id' => $root->branch_id,
        'product_category_id' => $sub->id,
        'name' => 'Consomé de camarón',
    ]);
    $item = OrderItem::factory()->create([
        'product_id' => $product->id,
        'product_name' => 'Sopas · Consomé de camarón',
        'metadata' => null,
    ]);

    expect(OrderData::itemCategoryContext($item->fresh()))->toBe([
        'category_name' => 'Sopas',
        'subcategory_name' => 'Mariscos',
        'display_name' => 'Consomé de camarón',
    ]);
});

test('admin order show exposes restaurant phone for contact actions', function () {
    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create(['phone' => '9631112233']);
    $branch = BusinessBranch::factory()->for($business)->create([
        'phone' => '9639998877',
    ]);
    $order = Order::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/orders/show')
            ->where('order.restaurant.phone', '9639998877'));
});

test('order data omits branch name when it matches business ignoring accents', function () {
    $business = Business::factory()->create(['name' => 'Taquería Yalchivol']);
    $branch = BusinessBranch::factory()->for($business)->create([
        'name' => 'Taqueria Yalchivol',
    ]);
    $order = Order::factory()->create([
        'branch_id' => $branch->id,
        'merchant_name_snapshot' => 'Taquería Yalchivol',
    ]);

    $data = OrderData::transform($order->fresh(['branch.business']));

    expect($data['restaurant']['name'])->toBe('Taquería Yalchivol')
        ->and($data['restaurant']['branch_name'])->toBeNull();
});

test('order data keeps branch name when it differs from business', function () {
    $business = Business::factory()->create(['name' => 'Sushi House']);
    $branch = BusinessBranch::factory()->for($business)->create([
        'name' => 'Sucursal Centro',
    ]);
    $order = Order::factory()->create([
        'branch_id' => $branch->id,
        'merchant_name_snapshot' => 'Sushi House',
    ]);

    $data = OrderData::transform($order->fresh(['branch.business']));

    expect($data['restaurant']['branch_name'])->toBe('Sucursal Centro');
});

test('admin order show exposes grouped item category fields', function () {
    $admin = User::factory()->systemAdmin()->create();
    $root = ProductCategory::factory()->create(['name' => 'Tacos']);
    $product = Product::factory()->create([
        'branch_id' => $root->branch_id,
        'product_category_id' => $root->id,
        'name' => 'Asada',
    ]);
    $order = Order::factory()->create([
        'branch_id' => $root->branch_id,
    ]);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => 'Tacos · Asada',
        'metadata' => [
            'category_name' => 'Tacos',
            'subcategory_name' => null,
            'product_display_name' => 'Asada',
        ],
        'quantity' => 3,
        'subtotal' => 90,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/orders/show')
            ->where('order.items.0.category_name', 'Tacos')
            ->where('order.items.0.display_name', 'Asada')
            ->where('order.items.0.subcategory_name', null));
});
