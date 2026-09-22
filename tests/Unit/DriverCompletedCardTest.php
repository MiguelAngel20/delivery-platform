<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderFinancial;
use App\Models\OrderItem;
use App\Models\OrderItemOption;
use App\Support\OrderData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('driver completed card includes modal detail payload', function () {
    $order = Order::factory()->create([
        'order_status' => OrderStatus::Delivered,
        'delivered_at' => now(),
        'total' => 150,
    ]);

    OrderFinancial::factory()->create([
        'order_id' => $order->id,
        'driver_earning' => 40,
        'driver_commission' => 10,
        'business_amount' => 100,
        'customer_total' => 150,
    ]);

    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_name' => 'Tacos',
        'quantity' => 3,
        'subtotal' => 90,
    ]);

    OrderItemOption::factory()->create([
        'order_item_id' => $item->id,
        'option_name' => 'Asada',
    ]);

    $card = OrderData::driverCompletedCard($order->fresh());

    expect($card)->toHaveKeys([
        'items',
        'payment',
        'pickup_address',
        'delivery_address',
        'gross_earning',
        'driver_commission',
    ])
        ->and($card['items'][0]['line_label'])->toContain('Tacos')
        ->and($card['items'][0]['line_label'])->toContain('->')
        ->and($card['payment']['business_payment'])->toBe('100.00')
        ->and($card['gross_earning'])->toBe('50.00')
        ->and($card['driver_earning'])->toBe('40.00');
});
