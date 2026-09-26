<?php

use App\Enums\OptionSelectionAction;
use App\Enums\ProductOptionGroupType;
use App\Models\OrderItem;
use App\Models\OrderItemOption;
use App\Support\OrderData;

test('item line label joins quantity product and options with arrows', function () {
    expect(OrderData::itemLineLabel('3.00', 'Tacos', ['ASADA', 'CON TODO']))
        ->toBe('3.00 - Tacos -> ASADA -> CON TODO');
});

test('item line label works without options', function () {
    expect(OrderData::itemLineLabel('1', 'Agua', []))
        ->toBe('1 - Agua');
});

test('options for display collapse same cluster into one header', function () {
    $picoso1 = new OrderItemOption([
        'option_name' => 'Picoso 1',
        'option_cluster_name' => 'Picosos',
        'option_type' => ProductOptionGroupType::Choice,
        'price_modifier' => 0,
        'selection_action' => OptionSelectionAction::Selected,
    ]);
    $picoso1->id = 1;

    $picoso2 = new OrderItemOption([
        'option_name' => 'Picoso 2',
        'option_cluster_name' => 'Picosos',
        'option_type' => ProductOptionGroupType::Choice,
        'price_modifier' => 0,
        'selection_action' => OptionSelectionAction::Selected,
    ]);
    $picoso2->id = 2;

    $dulce1 = new OrderItemOption([
        'option_name' => 'Dulce 1',
        'option_cluster_name' => 'Dulces',
        'option_type' => ProductOptionGroupType::Choice,
        'price_modifier' => 0,
        'selection_action' => OptionSelectionAction::Selected,
    ]);
    $dulce1->id = 3;

    $lines = OrderData::optionsForDisplay([$picoso1, $picoso2, $dulce1]);

    expect($lines)->toHaveCount(2)
        ->and($lines[0]['display'])->toBe('PICOSOS: PICOSO 1, PICOSO 2')
        ->and($lines[1]['display'])->toBe('DULCES: DULCE 1');
});

test('options for display keep unclustered options separate', function () {
    $asada = new OrderItemOption([
        'option_name' => 'Asada',
        'option_cluster_name' => null,
        'option_type' => ProductOptionGroupType::Choice,
        'price_modifier' => 0,
        'selection_action' => OptionSelectionAction::Selected,
    ]);
    $asada->id = 1;

    $cebolla = new OrderItemOption([
        'option_name' => 'Cebolla',
        'option_cluster_name' => null,
        'option_type' => ProductOptionGroupType::Removable,
        'price_modifier' => 0,
        'selection_action' => OptionSelectionAction::Removed,
    ]);
    $cebolla->id = 2;

    $lines = OrderData::optionsForDisplay([$asada, $cebolla]);

    expect($lines)->toHaveCount(2)
        ->and($lines[0]['display'])->toBe('ASADA')
        ->and($lines[1]['display'])->toBe('SIN CEBOLLA');
});

test('option display includes quantity for addon extras', function () {
    expect(OrderData::optionDisplay(
        'Queso',
        ProductOptionGroupType::Addon->value,
        OptionSelectionAction::Added->value,
        null,
        2,
    ))->toBe('2 QUESO');
});

test('item category context prefers metadata snapshot', function () {
    $item = new OrderItem([
        'product_name' => 'Tacos · Asada',
        'metadata' => [
            'category_name' => 'Tacos',
            'subcategory_name' => null,
            'product_display_name' => 'Asada',
        ],
    ]);

    expect(OrderData::itemCategoryContext($item))->toBe([
        'category_name' => 'Tacos',
        'subcategory_name' => null,
        'display_name' => 'Asada',
    ]);
});

test('item category context includes subcategory from metadata', function () {
    $item = new OrderItem([
        'product_name' => 'Sopas · Sopa de camarón',
        'metadata' => [
            'category_name' => 'Sopas',
            'subcategory_name' => 'Mariscos',
            'product_display_name' => 'Sopa de camarón',
        ],
    ]);

    expect(OrderData::itemCategoryContext($item))->toBe([
        'category_name' => 'Sopas',
        'subcategory_name' => 'Mariscos',
        'display_name' => 'Sopa de camarón',
    ]);
});

test('item category context parses legacy category prefix', function () {
    $item = new OrderItem([
        'product_name' => 'Quesadillas · Adobada',
        'metadata' => null,
    ]);

    expect(OrderData::itemCategoryContext($item))->toBe([
        'category_name' => 'Quesadillas',
        'subcategory_name' => null,
        'display_name' => 'Adobada',
    ]);
});
