<?php

use App\Support\OrderData;

test('item line label joins quantity product and options with arrows', function () {
    expect(OrderData::itemLineLabel('3.00', 'Tacos', ['ASADA', 'CON TODO']))
        ->toBe('3.00 - Tacos -> ASADA -> CON TODO');
});

test('item line label works without options', function () {
    expect(OrderData::itemLineLabel('1', 'Agua', []))
        ->toBe('1 - Agua');
});
