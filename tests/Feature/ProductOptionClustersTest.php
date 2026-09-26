<?php

use App\Actions\Orders\CreateOrder;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Enums\ProductOptionGroupType;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\BusinessUser;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionCluster;
use App\Models\ProductOptionGroup;
use App\Models\ProductPrice;
use App\Models\User;
use App\Support\OrderData;
use App\Support\StorefrontProductData;

function seedClusterCatalogAdmin(): array
{
    $admin = User::factory()->businessAdmin()->create();
    $business = Business::factory()->create([
        'operation_mode' => BusinessOperationMode::Partner,
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

test('product can be created with flat choice options without clusters', function () {
    ['admin' => $admin, 'branch' => $branch] = seedClusterCatalogAdmin();

    $this->actingAs($admin)
        ->post(route('business.products.store'), [
            'branch_id' => $branch->id,
            'name' => 'Tacos',
            'list_price' => 40,
            'is_available' => true,
            'is_active' => true,
            'option_groups' => [
                [
                    'name' => 'Variantes',
                    'type' => ProductOptionGroupType::Choice->value,
                    'is_required' => true,
                    'min_selection' => 1,
                    'max_selection' => 1,
                    'is_active' => true,
                    'has_option_clusters' => false,
                    'options' => [
                        ['name' => 'Asada', 'price_modifier' => 0, 'is_default' => false, 'is_available' => true],
                        ['name' => 'Pastor', 'price_modifier' => 0, 'is_default' => false, 'is_available' => true],
                    ],
                ],
            ],
        ])
        ->assertRedirect();

    $product = Product::query()->where('name', 'Tacos')->first();
    $group = $product->optionGroups()->first();

    expect($product)->not->toBeNull()
        ->and($group->has_option_clusters)->toBeFalse()
        ->and($group->clusters()->count())->toBe(0)
        ->and($group->options()->count())->toBe(2);
});

test('product can persist choice clusters and storefront payload includes them', function () {
    ['admin' => $admin, 'branch' => $branch, 'business' => $business] = seedClusterCatalogAdmin();

    $this->actingAs($admin)
        ->post(route('business.products.store'), [
            'branch_id' => $branch->id,
            'name' => 'Salsas',
            'list_price' => 20,
            'is_available' => true,
            'is_active' => true,
            'option_groups' => [
                [
                    'name' => 'Variantes',
                    'type' => ProductOptionGroupType::Choice->value,
                    'is_required' => true,
                    'min_selection' => 1,
                    'max_selection' => 4,
                    'is_active' => true,
                    'has_option_clusters' => true,
                    'clusters' => [
                        [
                            'name' => 'Picantes',
                            'options' => [
                                ['name' => 'Habanero', 'price_modifier' => 0, 'is_default' => false, 'is_available' => true],
                                ['name' => 'Chiltepín', 'price_modifier' => 0, 'is_default' => false, 'is_available' => true],
                            ],
                        ],
                        [
                            'name' => 'Agridulces',
                            'options' => [
                                ['name' => 'Mango', 'price_modifier' => 0, 'is_default' => false, 'is_available' => true],
                            ],
                        ],
                    ],
                    'options' => [
                        ['name' => 'Habanero', 'price_modifier' => 0, 'is_default' => false, 'is_available' => true],
                        ['name' => 'Chiltepín', 'price_modifier' => 0, 'is_default' => false, 'is_available' => true],
                        ['name' => 'Mango', 'price_modifier' => 0, 'is_default' => false, 'is_available' => true],
                    ],
                ],
            ],
        ])
        ->assertRedirect();

    $product = Product::query()->where('name', 'Salsas')->first();
    $group = $product->optionGroups()->with(['clusters.options', 'options'])->first();

    expect($group->has_option_clusters)->toBeTrue()
        ->and($group->clusters)->toHaveCount(2)
        ->and($group->clusters->first()->name)->toBe('Picantes')
        ->and($group->clusters->first()->options)->toHaveCount(2)
        ->and($group->options)->toHaveCount(3)
        ->and($group->options->every(fn (ProductOption $option): bool => $option->option_cluster_id !== null))->toBeTrue();

    $payload = StorefrontProductData::menuProduct($product, $business->slug);
    $choice = collect($payload['option_groups'])->firstWhere('type', 'choice');

    expect($choice['has_option_clusters'])->toBeTrue()
        ->and($choice['clusters'])->toHaveCount(2)
        ->and($choice['clusters'][0]['name'])->toBe('Picantes')
        ->and($choice['options'])->toHaveCount(3);
});

test('turning off clusters flattens options on update', function () {
    ['admin' => $admin, 'branch' => $branch] = seedClusterCatalogAdmin();

    $product = Product::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Salsas',
        'is_active' => true,
        'is_available' => true,
    ]);
    ProductPrice::factory()->create([
        'product_id' => $product->id,
        'list_price' => 20,
        'is_active' => true,
    ]);

    $group = ProductOptionGroup::factory()->create([
        'product_id' => $product->id,
        'type' => ProductOptionGroupType::Choice,
        'name' => 'Variantes',
        'has_option_clusters' => true,
        'min_selection' => 1,
        'max_selection' => 2,
    ]);
    $cluster = ProductOptionCluster::factory()->create([
        'option_group_id' => $group->id,
        'name' => 'Picantes',
    ]);
    ProductOption::factory()->create([
        'option_group_id' => $group->id,
        'option_cluster_id' => $cluster->id,
        'name' => 'Habanero',
    ]);

    $this->actingAs($admin)
        ->from(route('business.products.edit', $product))
        ->post(route('business.products.update', $product), [
            'name' => 'Salsas',
            'list_price' => 20,
            'is_available' => true,
            'is_active' => true,
            'option_groups' => [
                [
                    'name' => 'Variantes',
                    'type' => ProductOptionGroupType::Choice->value,
                    'is_required' => true,
                    'min_selection' => 1,
                    'max_selection' => 2,
                    'is_active' => true,
                    'has_option_clusters' => false,
                    'options' => [
                        ['name' => 'Habanero', 'price_modifier' => 0, 'is_default' => false, 'is_available' => true],
                        ['name' => 'Verde', 'price_modifier' => 0, 'is_default' => false, 'is_available' => true],
                    ],
                ],
            ],
        ])
        ->assertRedirect(route('business.products.edit', $product));

    $group = $product->fresh()->optionGroups()->with(['clusters', 'options'])->first();

    expect($group->has_option_clusters)->toBeFalse()
        ->and($group->clusters)->toHaveCount(0)
        ->and($group->options)->toHaveCount(2)
        ->and($group->options->every(fn (ProductOption $option): bool => $option->option_cluster_id === null))->toBeTrue();
});

test('has_option_clusters requires at least one named cluster with options', function () {
    ['admin' => $admin, 'branch' => $branch] = seedClusterCatalogAdmin();

    $this->actingAs($admin)
        ->from(route('business.products.create'))
        ->post(route('business.products.store'), [
            'branch_id' => $branch->id,
            'name' => 'Salsas',
            'list_price' => 20,
            'is_available' => true,
            'is_active' => true,
            'option_groups' => [
                [
                    'name' => 'Variantes',
                    'type' => ProductOptionGroupType::Choice->value,
                    'is_required' => true,
                    'min_selection' => 1,
                    'max_selection' => 2,
                    'is_active' => true,
                    'has_option_clusters' => true,
                    'clusters' => [],
                    'options' => [],
                ],
            ],
        ])
        ->assertSessionHasErrors()
        ->assertRedirect(route('business.products.create'));
});

test('create order stores option_cluster_name and order display includes cluster', function () {
    ['branch' => $branch] = seedClusterCatalogAdmin();

    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->for($user)->create();
    $address = CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'is_default' => true,
    ]);

    $product = Product::factory()->create([
        'branch_id' => $branch->id,
        'is_active' => true,
        'is_available' => true,
    ]);
    ProductPrice::factory()->create([
        'product_id' => $product->id,
        'list_price' => 50,
        'is_active' => true,
    ]);

    $group = ProductOptionGroup::factory()->create([
        'product_id' => $product->id,
        'type' => ProductOptionGroupType::Choice,
        'name' => 'Variantes',
        'has_option_clusters' => true,
        'is_required' => true,
        'min_selection' => 1,
        'max_selection' => 4,
    ]);
    $cluster = ProductOptionCluster::factory()->create([
        'option_group_id' => $group->id,
        'name' => 'Picantes',
    ]);
    $option = ProductOption::factory()->create([
        'option_group_id' => $group->id,
        'option_cluster_id' => $cluster->id,
        'name' => 'Habanero',
        'is_available' => true,
    ]);

    $order = app(CreateOrder::class)->handle($customer, $user, [
        'branch_id' => $branch->id,
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 1,
                'selected_options' => [
                    ['option_id' => $option->id, 'action' => 'selected'],
                ],
            ],
        ],
        'delivery' => [
            'source' => 'saved_address',
            'customer_address_id' => $address->id,
        ],
    ]);

    $orderOption = $order->items->first()->options->first();

    expect($orderOption->option_cluster_name)->toBe('Picantes')
        ->and(OrderData::optionDisplay(
            $orderOption->option_name,
            $orderOption->option_type->value,
            $orderOption->selection_action?->value,
            $orderOption->option_cluster_name,
        ))->toBe('PICANTES: HABANERO')
        ->and(OrderData::transform($order)['items'][0]['options'])
        ->toHaveCount(1)
        ->and(OrderData::transform($order)['items'][0]['options'][0]['display'])
        ->toBe('PICANTES: HABANERO');
});
test('customer can select multiple options from the same cluster within max', function () {
    ['branch' => $branch] = seedClusterCatalogAdmin();

    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->for($user)->create();
    $address = CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'is_default' => true,
    ]);

    $product = Product::factory()->create([
        'branch_id' => $branch->id,
        'is_active' => true,
        'is_available' => true,
    ]);
    ProductPrice::factory()->create([
        'product_id' => $product->id,
        'list_price' => 50,
        'is_active' => true,
    ]);

    $group = ProductOptionGroup::factory()->create([
        'product_id' => $product->id,
        'type' => ProductOptionGroupType::Choice,
        'has_option_clusters' => true,
        'min_selection' => 1,
        'max_selection' => 4,
        'is_required' => true,
    ]);
    $cluster = ProductOptionCluster::factory()->create([
        'option_group_id' => $group->id,
        'name' => 'Picantes',
    ]);
    $habanero = ProductOption::factory()->create([
        'option_group_id' => $group->id,
        'option_cluster_id' => $cluster->id,
        'name' => 'Habanero',
    ]);
    $chile = ProductOption::factory()->create([
        'option_group_id' => $group->id,
        'option_cluster_id' => $cluster->id,
        'name' => 'Chiltepín',
    ]);
    $mango = ProductOption::factory()->create([
        'option_group_id' => $group->id,
        'option_cluster_id' => $cluster->id,
        'name' => 'Mango picante',
    ]);
    $extra = ProductOption::factory()->create([
        'option_group_id' => $group->id,
        'option_cluster_id' => $cluster->id,
        'name' => 'Extra chile',
    ]);

    $order = app(CreateOrder::class)->handle($customer, $user, [
        'branch_id' => $branch->id,
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 1,
                'selected_options' => [
                    ['option_id' => $habanero->id, 'action' => 'selected'],
                    ['option_id' => $chile->id, 'action' => 'selected'],
                    ['option_id' => $mango->id, 'action' => 'selected'],
                    ['option_id' => $extra->id, 'action' => 'selected'],
                ],
            ],
        ],
        'delivery' => [
            'source' => 'saved_address',
            'customer_address_id' => $address->id,
        ],
    ]);

    expect($order->items->first()->options)->toHaveCount(4)
        ->and(OrderData::optionsForDisplay($order->items->first()->options))
        ->toHaveCount(1)
        ->and(OrderData::optionsForDisplay($order->items->first()->options)[0]['display'])
        ->toBe('PICANTES: HABANERO, CHILTEPÍN, MANGO PICANTE, EXTRA CHILE');
});
