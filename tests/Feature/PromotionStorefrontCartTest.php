<?php

use App\Actions\Orders\CreateOrder;
use App\Enums\ProductOptionGroupType;
use App\Enums\PromotionStatus;
use App\Models\BusinessBranch;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use App\Models\ProductPrice;
use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Models\User;
use App\Support\StorefrontPromotionData;

test('cart promotion endpoint returns items with option groups', function () {
    $branch = BusinessBranch::factory()->create();
    $product = Product::factory()->create([
        'branch_id' => $branch->id,
        'allow_special_instructions' => true,
    ]);
    ProductPrice::factory()->create(['product_id' => $product->id]);

    $group = ProductOptionGroup::factory()->choice()->create([
        'product_id' => $product->id,
        'name' => 'Salsa',
    ]);
    ProductOption::factory()->create([
        'option_group_id' => $group->id,
        'name' => 'BBQ',
    ]);

    $promotion = Promotion::factory()->create([
        'branch_id' => $branch->id,
        'status' => PromotionStatus::Active,
        'promotion_price' => 150,
    ]);

    PromotionItem::factory()->create([
        'promotion_id' => $promotion->id,
        'product_id' => $product->id,
        'name' => $product->name,
        'is_external_item' => false,
    ]);

    PromotionItem::factory()->create([
        'promotion_id' => $promotion->id,
        'product_id' => null,
        'name' => 'Bebida',
        'is_external_item' => true,
        'option_groups' => [
            [
                'name' => 'Variantes',
                'type' => ProductOptionGroupType::Choice->value,
                'is_required' => true,
                'min_selection' => 1,
                'max_selection' => 1,
                'is_active' => true,
                'options' => [
                    ['name' => 'Chico', 'price_modifier' => 0, 'is_default' => true, 'is_available' => true],
                    ['name' => 'Grande', 'price_modifier' => 0, 'is_default' => false, 'is_available' => true],
                ],
            ],
        ],
    ]);

    $this->getJson(route('cart.promotions.show', $promotion))
        ->assertOk()
        ->assertJsonPath('promotion.id', $promotion->id)
        ->assertJsonPath('promotion.items.0.option_groups.0.name', 'Salsa')
        ->assertJsonPath('promotion.items.1.option_groups.0.options.0.name', 'Chico');
});

test('create order accepts promotion line with customized items', function () {
    $branch = BusinessBranch::factory()->create();
    $customer = Customer::factory()->create();
    $user = $customer->user;
    $address = CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'is_active' => true,
    ]);

    $product = Product::factory()->create(['branch_id' => $branch->id]);
    ProductPrice::factory()->create(['product_id' => $product->id, 'list_price' => 99]);

    $group = ProductOptionGroup::factory()->choice()->create([
        'product_id' => $product->id,
        'name' => 'Salsa',
        'min_selection' => 1,
        'max_selection' => 1,
        'is_required' => true,
    ]);
    $option = ProductOption::factory()->create([
        'option_group_id' => $group->id,
        'name' => 'BBQ',
    ]);

    $promotion = Promotion::factory()->create([
        'branch_id' => $branch->id,
        'status' => PromotionStatus::Active,
        'promotion_price' => 120,
    ]);

    $menuItem = PromotionItem::factory()->create([
        'promotion_id' => $promotion->id,
        'product_id' => $product->id,
        'name' => $product->name,
        'is_external_item' => false,
    ]);

    $externalItem = PromotionItem::factory()->create([
        'promotion_id' => $promotion->id,
        'product_id' => null,
        'name' => 'Bebida',
        'is_external_item' => true,
        'option_groups' => [
            [
                'name' => 'Variantes',
                'type' => ProductOptionGroupType::Choice->value,
                'is_required' => true,
                'min_selection' => 1,
                'max_selection' => 1,
                'is_active' => true,
                'options' => [
                    ['name' => 'Chico', 'price_modifier' => 0, 'is_default' => true, 'is_available' => true],
                ],
            ],
        ],
    ]);

    $externalOptionId = StorefrontPromotionData::externalOptionId($externalItem->id, 0, 0);

    $order = app(CreateOrder::class)->handle($customer, $user, [
        'branch_id' => $branch->id,
        'items' => [
            [
                'promotion_id' => $promotion->id,
                'quantity' => 1,
                'promotion_items' => [
                    [
                        'promotion_item_id' => $menuItem->id,
                        'selected_options' => [
                            ['option_id' => $option->id, 'action' => 'selected'],
                        ],
                    ],
                    [
                        'promotion_item_id' => $externalItem->id,
                        'selected_options' => [
                            ['option_id' => $externalOptionId, 'action' => 'selected'],
                        ],
                    ],
                ],
            ],
        ],
        'delivery' => [
            'source' => 'saved_address',
            'customer_address_id' => $address->id,
        ],
    ]);

    expect($order->items)->toHaveCount(1)
        ->and($order->items->first()?->promotion_id)->toBe($promotion->id)
        ->and($order->items->first()?->product_id)->toBeNull()
        ->and((string) $order->items->first()?->subtotal)->toBe('120.00')
        ->and($order->items->first()?->options)->toHaveCount(2);
});
