<?php

use App\Actions\Orders\AcceptBusinessOrder;
use App\Actions\Orders\AcknowledgeOrder;
use App\Enums\BusinessOperationMode;
use App\Enums\OptionSelectionAction;
use App\Enums\OrderStatus;
use App\Enums\ProductOptionGroupType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemOption;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use App\Models\User;
use App\Support\OrderData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('customer timeline exposes five simplified milestones', function () {
    $order = Order::factory()->create([
        'order_status' => OrderStatus::PendingBusiness,
    ]);

    OrderStatusHistory::factory()->create([
        'order_id' => $order->id,
        'status' => OrderStatus::PendingBusiness,
    ]);

    $timeline = OrderData::customerTimeline($order->fresh(['statusHistory']));

    expect($timeline)->toHaveCount(5)
        ->and(collect($timeline)->pluck('label')->all())->toBe([
            'Esperando confirmación',
            'Tu pedido se está preparando',
            'Tu pedido va en camino',
            'Tu pedido ya está afuera de tu domicilio',
            'Entregado',
        ])
        ->and($timeline[0]['current'])->toBeTrue()
        ->and($timeline[0]['done'])->toBeFalse()
        ->and($timeline[1]['done'])->toBeFalse();
});

test('customer timeline advances when platform admin accepts the order', function () {
    $admin = User::factory()->systemAdmin()->create();
    $order = Order::factory()->create([
        'operation_mode' => BusinessOperationMode::PlatformOperated,
        'order_status' => OrderStatus::PendingPlatform,
    ]);

    OrderStatusHistory::factory()->create([
        'order_id' => $order->id,
        'status' => OrderStatus::PendingPlatform,
    ]);

    $before = OrderData::customerTimeline($order->fresh(['statusHistory']));
    $beforeGuidance = OrderData::customerStatusGuidance($order->fresh(['statusHistory', 'branch.business']));

    expect($before[0]['current'])->toBeTrue()
        ->and($before[0]['done'])->toBeFalse()
        ->and($before[1]['done'])->toBeFalse()
        ->and($before[1]['current'])->toBeFalse()
        ->and($beforeGuidance['title'] ?? null)->toBe('Estamos revisando tu pedido');

    $acknowledged = app(AcknowledgeOrder::class)->handle($order, $admin);
    $confirmingTimeline = OrderData::customerTimeline($acknowledged->fresh(['statusHistory']));
    $confirmingGuidance = OrderData::customerStatusGuidance($acknowledged->fresh(['statusHistory', 'branch.business']));

    expect($acknowledged->order_status)->toBe(OrderStatus::Accepted)
        ->and($confirmingTimeline[0]['current'])->toBeTrue()
        ->and($confirmingTimeline[0]['label'])->toBe('Pedido en confirmación')
        ->and($confirmingTimeline[0]['done'])->toBeTrue()
        ->and($confirmingGuidance['title'] ?? null)->toBe('Tu pedido está en confirmación');

    $updated = app(AcceptBusinessOrder::class)->handle($acknowledged, $admin, 20);
    $timeline = OrderData::customerTimeline($updated->fresh(['statusHistory']));
    $guidance = OrderData::customerStatusGuidance($updated->fresh(['statusHistory', 'branch.business']));

    expect($timeline[0]['done'])->toBeTrue()
        ->and($timeline[0]['label'])->toBe('Pedido recibido')
        ->and($timeline[0]['current'])->toBeFalse()
        ->and($timeline[1]['current'])->toBeTrue()
        ->and($timeline[1]['done'])->toBeTrue()
        ->and($timeline[1]['label'])->toBe('Tu pedido se está preparando')
        ->and($updated->order_status->customerLabel())->toBe('Tu pedido se está preparando')
        ->and($guidance['message'] ?? null)->toContain('20 minutos');
});

test('customer timeline marks on the way step when order is picked up', function () {
    $order = Order::factory()->create([
        'order_status' => OrderStatus::PickedUp,
    ]);

    foreach ([
        OrderStatus::PendingPlatform,
        OrderStatus::Preparing,
        OrderStatus::PickedUp,
    ] as $status) {
        OrderStatusHistory::factory()->create([
            'order_id' => $order->id,
            'status' => $status,
        ]);
    }

    $timeline = OrderData::customerTimeline($order->fresh(['statusHistory']));

    expect($timeline[2]['label'])->toBe('Tu pedido va en camino')
        ->and($timeline[2]['current'])->toBeTrue()
        ->and($timeline[2]['done'])->toBeTrue()
        ->and($timeline[3]['done'])->toBeFalse();
});

test('customer timeline marks completed steps when order is on the way to customer', function () {
    $order = Order::factory()->create([
        'order_status' => OrderStatus::OnTheWay,
    ]);

    foreach ([
        OrderStatus::PendingBusiness,
        OrderStatus::Accepted,
        OrderStatus::Preparing,
        OrderStatus::PickedUp,
        OrderStatus::OnTheWay,
    ] as $status) {
        OrderStatusHistory::factory()->create([
            'order_id' => $order->id,
            'status' => $status,
        ]);
    }

    $timeline = OrderData::customerTimeline($order->fresh(['statusHistory']));

    expect($timeline[0]['done'])->toBeTrue()
        ->and($timeline[1]['done'])->toBeTrue()
        ->and($timeline[2]['done'])->toBeTrue()
        ->and($timeline[3]['current'])->toBeTrue()
        ->and($timeline[3]['done'])->toBeTrue()
        ->and($timeline[4]['done'])->toBeFalse();
});

test('customer timeline marks all steps done when delivered', function () {
    $order = Order::factory()->create([
        'order_status' => OrderStatus::Delivered,
    ]);

    foreach ([
        OrderStatus::PendingBusiness,
        OrderStatus::Preparing,
        OrderStatus::PickedUp,
        OrderStatus::OnTheWay,
        OrderStatus::Delivered,
    ] as $status) {
        OrderStatusHistory::factory()->create([
            'order_id' => $order->id,
            'status' => $status,
        ]);
    }

    $timeline = OrderData::customerTimeline($order->fresh(['statusHistory']));

    expect(collect($timeline)->every(fn (array $step): bool => $step['done'] === true))->toBeTrue()
        ->and(collect($timeline)->contains(fn (array $step): bool => ($step['current'] ?? false) === true))->toBeFalse();
});

test('internal timeline keeps full status history for admin views', function () {
    $order = Order::factory()->create([
        'order_status' => OrderStatus::DriverAssigned,
    ]);

    foreach ([
        OrderStatus::PendingBusiness,
        OrderStatus::Accepted,
        OrderStatus::DriverAssigned,
    ] as $status) {
        OrderStatusHistory::factory()->create([
            'order_id' => $order->id,
            'status' => $status,
        ]);
    }

    $timeline = OrderData::timeline($order->fresh(['statusHistory']));

    expect($timeline)->toHaveCount(3)
        ->and($timeline[0]['label'])->toBe('Nuevo')
        ->and($timeline[1]['label'])->toBe('En confirmación')
        ->and($timeline[2]['label'])->toBe('Repartidor asignado')
        ->and($timeline[2]['current'])->toBeTrue();
});

test('customer order detail includes simplified customer timeline', function () {
    $order = Order::factory()->create([
        'order_status' => OrderStatus::Preparing,
        'estimated_preparation_minutes' => 15,
    ]);

    OrderStatusHistory::factory()->create([
        'order_id' => $order->id,
        'status' => OrderStatus::PendingBusiness,
    ]);
    OrderStatusHistory::factory()->create([
        'order_id' => $order->id,
        'status' => OrderStatus::Preparing,
    ]);

    $payload = OrderData::transform($order->fresh([
        'items.options',
        'addresses',
        'statusHistory',
        'branch.business',
        'customer.user',
        'customer.metrics',
        'assignedDriver.user',
        'financial',
        'financialTransactions',
        'payment',
        'cancellation.cancelledBy',
        'incidents',
        'driverRating',
        'quotes.items',
    ]));

    expect($payload)->toHaveKey('customer_timeline')
        ->and($payload['customer_timeline'])->toHaveCount(5)
        ->and($payload['customer_timeline'][0]['done'])->toBeTrue()
        ->and($payload['customer_timeline'][0]['label'])->toBe('Pedido recibido')
        ->and($payload['customer_timeline'][1]['current'])->toBeTrue()
        ->and($payload['customer_timeline'][1]['done'])->toBeTrue()
        ->and($payload['customer_status_guidance']['message'] ?? null)->toContain('15 minutos')
        ->and($payload['timeline'])->toHaveCount(2);
});

test('customer status guidance disappears once order is on the way', function () {
    $order = Order::factory()->create([
        'order_status' => OrderStatus::OnTheWay,
        'estimated_preparation_minutes' => 15,
    ]);

    expect(OrderData::customerStatusGuidance($order))->toBeNull();
});

test('customer status guidance for rejected orders suggests editing the cart', function () {
    $order = Order::factory()->create([
        'order_status' => OrderStatus::Rejected,
    ]);

    OrderStatusHistory::factory()->create([
        'order_id' => $order->id,
        'status' => OrderStatus::Rejected,
        'notes' => 'Ya no hay salsa grande',
    ]);

    $guidance = OrderData::customerStatusGuidance($order->fresh(['statusHistory', 'branch.business']));

    expect($guidance['title'] ?? null)->toBe('Pedido rechazado')
        ->and($guidance['message'] ?? null)->toContain('Ya no hay salsa grande')
        ->and($guidance['message'] ?? null)->toContain('editar tu carrito')
        ->and(collect($guidance['actions'] ?? [])->pluck('type')->all())->toContain('cart');
});

test('a rejected order refills the cart and keeps its own number', function () {
    $order = Order::factory()->create([
        'order_status' => OrderStatus::Rejected,
    ]);
    $product = Product::factory()->create([
        'branch_id' => $order->branch_id,
        'name' => 'Hamburguesa',
    ]);
    $group = ProductOptionGroup::factory()->addon()->create([
        'product_id' => $product->id,
    ]);
    $option = ProductOption::factory()->create([
        'option_group_id' => $group->id,
        'name' => 'Queso',
        'price_modifier' => 15,
    ]);
    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => 'Hamburguesa',
        'quantity' => 2,
        'unit_list_price' => 80,
        'unit_final_price' => 95,
        'metadata' => ['product_display_name' => 'Hamburguesa'],
    ]);
    OrderItemOption::factory()->create([
        'order_item_id' => $item->id,
        'product_option_id' => $option->id,
        'option_name' => 'Queso',
        'option_type' => ProductOptionGroupType::Addon,
        'price_modifier' => 15,
        'selection_action' => OptionSelectionAction::Added,
        'quantity' => 1,
    ]);

    $cart = OrderData::reorderCart($order->fresh());

    expect($cart)->not->toBeNull()
        ->and($cart['branchId'])->toBe($order->branch_id)
        ->and($cart['lines'])->toHaveCount(1)
        ->and($cart['lines'][0]['productId'])->toBe((string) $product->id)
        ->and($cart['lines'][0]['quantity'])->toBe(2)
        ->and($cart['lines'][0]['unitPrice'])->toBe(80.0)
        ->and($cart['lines'][0]['name'])->toBe('Hamburguesa')
        ->and($cart['lines'][0]['extras'][0]['name'])->toBe('Queso')
        ->and($cart['lines'][0]['selectedOptions'][0]['option_id'])->toBe($option->id)
        ->and($cart['lines'][0]['selectedOptions'][0]['group_id'])->toBe($group->id);

    expect(OrderData::reorderCart($order->fresh()->forceFill([
        'order_status' => OrderStatus::Preparing,
    ])))->toBeNull();
});
