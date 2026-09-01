<?php

use App\Enums\BusinessOperationMode;
use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Enums\ProductOptionGroupType;
use App\Enums\PromotionStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\BusinessUser;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use App\Models\ProductPrice;
use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Models\User;

function seedPromotionCustomizationAdmin(): array
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

test('external promotion item persists option_groups', function () {
    ['admin' => $admin, 'branch' => $branch] = seedPromotionCustomizationAdmin();

    $this->actingAs($admin)
        ->post(route('business.promotions.store'), [
            'branch_id' => $branch->id,
            'name' => 'Combo con extras',
            'promotion_price' => 150,
            'status' => PromotionStatus::Active->value,
            'items' => [
                [
                    'is_external_item' => true,
                    'product_id' => null,
                    'name' => 'Bebida',
                    'quantity' => 1,
                    'option_groups' => [
                        [
                            'name' => 'Variantes',
                            'type' => ProductOptionGroupType::Choice->value,
                            'is_required' => true,
                            'min_selection' => 1,
                            'max_selection' => 1,
                            'is_active' => true,
                            'options' => [
                                [
                                    'name' => 'Chico',
                                    'price_modifier' => 0,
                                    'is_default' => true,
                                    'is_available' => true,
                                ],
                                [
                                    'name' => 'Grande',
                                    'price_modifier' => 10,
                                    'is_default' => false,
                                    'is_available' => true,
                                ],
                            ],
                        ],
                        [
                            'name' => 'Extras',
                            'type' => ProductOptionGroupType::Addon->value,
                            'is_required' => false,
                            'min_selection' => 0,
                            'max_selection' => 2,
                            'is_active' => true,
                            'options' => [
                                [
                                    'name' => 'Hielo extra',
                                    'price_modifier' => 5,
                                    'is_default' => false,
                                    'is_available' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ])
        ->assertRedirect();

    $item = PromotionItem::query()
        ->where('name', 'Bebida')
        ->where('is_external_item', true)
        ->first();

    expect($item)->not->toBeNull()
        ->and($item?->option_groups)->toBeArray()
        ->and($item?->option_groups)->toHaveCount(2)
        ->and($item?->option_groups[0]['type'])->toBe(ProductOptionGroupType::Choice->value)
        ->and($item?->option_groups[1]['options'][0]['name'])->toBe('Hielo extra');
});

test('menu promotion item stores null option_groups', function () {
    ['admin' => $admin, 'branch' => $branch] = seedPromotionCustomizationAdmin();
    $product = Product::factory()->create(['branch_id' => $branch->id]);
    ProductPrice::factory()->create(['product_id' => $product->id, 'list_price' => 99]);

    $group = ProductOptionGroup::factory()->choice()->create([
        'product_id' => $product->id,
        'name' => 'Salsa',
    ]);
    ProductOption::factory()->create([
        'option_group_id' => $group->id,
        'name' => 'BBQ',
    ]);

    $this->actingAs($admin)
        ->post(route('business.promotions.store'), [
            'branch_id' => $branch->id,
            'name' => 'Combo menú personalizado',
            'promotion_price' => 120,
            'status' => PromotionStatus::Active->value,
            'items' => [
                [
                    'is_external_item' => false,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'quantity' => 1,
                    'option_groups' => [
                        [
                            'name' => 'Ignorado',
                            'type' => ProductOptionGroupType::Choice->value,
                            'is_required' => true,
                            'min_selection' => 1,
                            'max_selection' => 1,
                            'options' => [
                                ['name' => 'No debe guardarse'],
                            ],
                        ],
                    ],
                ],
            ],
        ])
        ->assertRedirect();

    $item = PromotionItem::query()
        ->where('promotion_id', Promotion::query()->where('name', 'Combo menú personalizado')->value('id'))
        ->first();

    expect($item)->not->toBeNull()
        ->and($item?->product_id)->toBe($product->id)
        ->and($item?->option_groups)->toBeNull();
});

test('business admin can fetch product customization json', function () {
    ['admin' => $admin, 'branch' => $branch] = seedPromotionCustomizationAdmin();
    $product = Product::factory()->create(['branch_id' => $branch->id, 'name' => 'Pizza']);

    $group = ProductOptionGroup::factory()->addon()->create([
        'product_id' => $product->id,
        'name' => 'Extras',
        'min_selection' => 0,
        'max_selection' => 3,
    ]);
    ProductOption::factory()->create([
        'option_group_id' => $group->id,
        'name' => 'Queso extra',
        'price_modifier' => 15,
    ]);

    $this->actingAs($admin)
        ->getJson(route('business.products.customization', $product))
        ->assertOk()
        ->assertJsonPath('option_groups.0.name', 'Extras')
        ->assertJsonPath('option_groups.0.type', ProductOptionGroupType::Addon->value)
        ->assertJsonPath('option_groups.0.options.0.name', 'Queso extra')
        ->assertJsonPath('option_groups.0.options.0.price_modifier', '15.00');
});
