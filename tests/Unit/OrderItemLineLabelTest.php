<?php

use App\Models\OrderItem;
use App\Support\OrderData;

test('item line label joins quantity product and options with arrows', function () {
    expect(OrderData::itemLineLabel('3.00', 'Tacos', ['ASADA', 'CON TODO']))
        ->toBe('3.00 - Tacos -> ASADA -> CON TODO');
});

test('item line label works without options', function () {
    expect(OrderData::itemLineLabel('1', 'Agua', []))
        ->toBe('1 - Agua');
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
