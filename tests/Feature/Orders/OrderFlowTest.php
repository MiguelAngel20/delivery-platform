<?php

use App\Actions\Orders\AcceptBusinessOrder;
use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\MarkOrderReady;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Enums\OptionSelectionAction;
use App\Enums\OrderStatus;
use App\Enums\ProductOptionGroupType;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\BusinessUser;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Driver;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use App\Models\ProductPrice;
use App\Models\User;
use App\Services\Orders\OrderStateService;
use App\Support\BusinessHours;
use Illuminate\Validation\ValidationException;

function seedOrderCustomer(): array
{
    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->for($user)->create();
    $address = CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'is_default' => true,
    ]);

    return compact('user', 'customer', 'address');
}

function seedOrderCatalog(): array
{
    $business = Business::factory()->create([
        'operation_mode' => BusinessOperationMode::Partner,
        'status' => BusinessStatus::Active,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create();
    $product = Product::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Hamburguesa clásica',
        'is_active' => true,
        'is_available' => true,
        'allow_special_instructions' => true,
    ]);
    ProductPrice::factory()->create([
        'product_id' => $product->id,
        'list_price' => 105,
        'is_active' => true,
    ]);

    $removable = ProductOptionGroup::factory()->removable()->create([
        'product_id' => $product->id,
        'name' => 'Ingredientes',
    ]);
    $onion = ProductOption::factory()->create([
        'option_group_id' => $removable->id,
        'name' => 'Cebolla',
        'is_default' => true,
        'price_modifier' => 0,
    ]);

    $addon = ProductOptionGroup::factory()->addon()->create([
        'product_id' => $product->id,
        'name' => 'Extras',
    ]);
    $cheese = ProductOption::factory()->create([
        'option_group_id' => $addon->id,
        'name' => 'Queso extra',
        'price_modifier' => 15,
    ]);

    return compact('business', 'branch', 'product', 'onion', 'cheese');
}

function validOrderPayload(BusinessBranch $branch, Product $product, ProductOption $onion, ProductOption $cheese, CustomerAddress $address): array
{
    return [
        'branch_id' => $branch->id,
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 1,
                'special_instructions' => 'Bien cocida',
                'selected_options' => [
                    [
                        'option_id' => $onion->id,
                        'action' => OptionSelectionAction::Removed->value,
                    ],
                    [
                        'option_id' => $cheese->id,
                        'action' => OptionSelectionAction::Added->value,
                    ],
                ],
            ],
        ],
        'delivery' => [
            'source' => 'saved_address',
            'customer_address_id' => $address->id,
        ],
    ];
}

test('customer can create valid order', function () {
    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedOrderCustomer();
    ['branch' => $branch, 'product' => $product, 'onion' => $onion, 'cheese' => $cheese] = seedOrderCatalog();

    $order = app(CreateOrder::class)->handle(
        $customer,
        $user,
        validOrderPayload($branch, $product, $onion, $cheese, $address),
    );

    expect($order->order_status)->toBe(OrderStatus::PendingBusiness)
        ->and($order->order_number)->toStartWith('RIDE-')
        ->and($order->payment_method->value)->toBe('cash')
        ->and($order->items)->toHaveCount(1);
});

test('customer can store order via http endpoint', function () {
    ['user' => $user, 'address' => $address] = seedOrderCustomer();
    ['branch' => $branch, 'product' => $product, 'onion' => $onion, 'cheese' => $cheese] = seedOrderCatalog();

    $this->actingAs($user)
        ->post(route('customer.orders.store'), validOrderPayload($branch, $product, $onion, $cheese, $address))
        ->assertRedirect();

    $order = Order::query()->first();

    expect($order)->not->toBeNull()
        ->and($order?->order_status)->toBe(OrderStatus::PendingBusiness);
});

test('create order accepts selected action for addon extras', function () {
    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedOrderCustomer();
    ['branch' => $branch, 'product' => $product, 'onion' => $onion, 'cheese' => $cheese] = seedOrderCatalog();

    $payload = validOrderPayload($branch, $product, $onion, $cheese, $address);
    $payload['items'][0]['selected_options'][1]['action'] = 'selected';

    $order = app(CreateOrder::class)->handle($customer, $user, $payload);

    expect($order->items->first()?->options)->toHaveCount(2)
        ->and($order->items->first()?->options->firstWhere('option_name', 'Queso extra')?->selection_action)
        ->toBe(OptionSelectionAction::Added);
});

test('order stores price and product name snapshots', function () {
    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedOrderCustomer();
    ['branch' => $branch, 'product' => $product, 'onion' => $onion, 'cheese' => $cheese] = seedOrderCatalog();

    $order = app(CreateOrder::class)->handle(
        $customer,
        $user,
        validOrderPayload($branch, $product, $onion, $cheese, $address),
    );

    $item = $order->items->first();

    expect($item?->product_name)->toBe('Hamburguesa clásica')
        ->and((string) $item?->unit_list_price)->toBe('105.00')
        ->and((string) $item?->unit_final_price)->toBe('120.00');
});

test('order stores option snapshots', function () {
    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedOrderCustomer();
    ['branch' => $branch, 'product' => $product, 'onion' => $onion, 'cheese' => $cheese] = seedOrderCatalog();

    $order = app(CreateOrder::class)->handle(
        $customer,
        $user,
        validOrderPayload($branch, $product, $onion, $cheese, $address),
    );

    $options = $order->items->first()?->options;

    expect($options)->toHaveCount(2)
        ->and($options?->firstWhere('option_name', 'Cebolla')?->selection_action)->toBe(OptionSelectionAction::Removed)
        ->and($options?->firstWhere('option_name', 'Cebolla')?->option_type)->toBe(ProductOptionGroupType::Removable)
        ->and($options?->firstWhere('option_name', 'Queso extra')?->selection_action)->toBe(OptionSelectionAction::Added);
});

test('order stores pickup and delivery snapshots', function () {
    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedOrderCustomer();
    ['branch' => $branch, 'product' => $product, 'onion' => $onion, 'cheese' => $cheese] = seedOrderCatalog();

    $order = app(CreateOrder::class)->handle(
        $customer,
        $user,
        validOrderPayload($branch, $product, $onion, $cheese, $address),
    );

    expect($order->addresses)->toHaveCount(2)
        ->and($order->pickupAddress?->address_text)->toBe($branch->address_text)
        ->and($order->deliveryAddress?->address_text)->toBe($address->address_text);
});

test('customer cannot order unavailable product', function () {
    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedOrderCustomer();
    ['branch' => $branch, 'product' => $product, 'onion' => $onion, 'cheese' => $cheese] = seedOrderCatalog();

    $product->update(['is_available' => false]);

    expect(fn () => app(CreateOrder::class)->handle(
        $customer,
        $user,
        validOrderPayload($branch, $product, $onion, $cheese, $address),
    ))->toThrow(ValidationException::class);
});

test('customer cannot order from a closed branch', function () {
    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedOrderCustomer();
    ['business' => $business, 'branch' => $branch, 'product' => $product, 'onion' => $onion, 'cheese' => $cheese] = seedOrderCatalog();

    $branch->update([
        'opening_hours' => collect(BusinessHours::dayKeys())
            ->map(fn (string $day): array => [
                'day' => $day,
                'is_open' => false,
                'opens_at' => null,
                'closes_at' => null,
            ])
            ->all(),
    ]);

    expect(fn () => app(CreateOrder::class)->handle(
        $customer,
        $user,
        validOrderPayload($branch, $product, $onion, $cheese, $address),
    ))->toThrow(ValidationException::class, 'Esta sucursal está cerrada en este momento.');
});

test('customer cannot use option from another product', function () {
    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedOrderCustomer();
    ['branch' => $branch, 'product' => $product, 'onion' => $onion, 'cheese' => $cheese] = seedOrderCatalog();

    $otherProduct = Product::factory()->create(['branch_id' => $branch->id]);
    ProductPrice::factory()->create(['product_id' => $otherProduct->id, 'list_price' => 50, 'is_active' => true]);
    $otherGroup = ProductOptionGroup::factory()->choice()->create(['product_id' => $otherProduct->id]);
    $foreignOption = ProductOption::factory()->create(['option_group_id' => $otherGroup->id]);

    $payload = validOrderPayload($branch, $product, $onion, $cheese, $address);
    $payload['items'][0]['selected_options'][] = [
        'option_id' => $foreignOption->id,
        'action' => OptionSelectionAction::Selected->value,
    ];

    expect(fn () => app(CreateOrder::class)->handle($customer, $user, $payload))
        ->toThrow(ValidationException::class);
});

test('customer cannot order choice group below min selection', function () {
    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedOrderCustomer();
    ['branch' => $branch, 'product' => $product, 'onion' => $onion, 'cheese' => $cheese] = seedOrderCatalog();

    $choice = ProductOptionGroup::factory()->choice()->create([
        'product_id' => $product->id,
        'name' => 'Variantes',
        'min_selection' => 2,
        'max_selection' => 4,
    ]);
    $bbq = ProductOption::factory()->create([
        'option_group_id' => $choice->id,
        'name' => 'BBQ',
    ]);

    $payload = validOrderPayload($branch, $product, $onion, $cheese, $address);
    $payload['items'][0]['selected_options'][] = [
        'option_id' => $bbq->id,
        'action' => OptionSelectionAction::Selected->value,
    ];

    expect(fn () => app(CreateOrder::class)->handle($customer, $user, $payload))
        ->toThrow(ValidationException::class);
});

test('customer cannot order addon group above max selection', function () {
    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedOrderCustomer();
    ['branch' => $branch, 'product' => $product, 'onion' => $onion, 'cheese' => $cheese] = seedOrderCatalog();

    $addonGroup = ProductOptionGroup::query()->where('name', 'Extras')->firstOrFail();
    $addonGroup->update(['max_selection' => 1]);

    $bacon = ProductOption::factory()->create([
        'option_group_id' => $addonGroup->id,
        'name' => 'Tocino',
        'price_modifier' => 10,
    ]);

    $payload = validOrderPayload($branch, $product, $onion, $cheese, $address);
    $payload['items'][0]['selected_options'][] = [
        'option_id' => $bacon->id,
        'action' => OptionSelectionAction::Added->value,
    ];

    expect(fn () => app(CreateOrder::class)->handle($customer, $user, $payload))
        ->toThrow(ValidationException::class);
});

test('customer can order choice group within min and max selection', function () {
    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedOrderCustomer();
    ['branch' => $branch, 'product' => $product, 'onion' => $onion, 'cheese' => $cheese] = seedOrderCatalog();

    $choice = ProductOptionGroup::factory()->choice()->create([
        'product_id' => $product->id,
        'name' => 'Variantes',
        'min_selection' => 2,
        'max_selection' => 4,
    ]);
    $bbq = ProductOption::factory()->create([
        'option_group_id' => $choice->id,
        'name' => 'BBQ',
    ]);
    $ranch = ProductOption::factory()->create([
        'option_group_id' => $choice->id,
        'name' => 'Ranch',
    ]);

    $payload = validOrderPayload($branch, $product, $onion, $cheese, $address);
    $payload['items'][0]['selected_options'][] = [
        'option_id' => $bbq->id,
        'action' => OptionSelectionAction::Selected->value,
    ];
    $payload['items'][0]['selected_options'][] = [
        'option_id' => $ranch->id,
        'action' => OptionSelectionAction::Selected->value,
    ];

    $order = app(CreateOrder::class)->handle($customer, $user, $payload);

    expect($order->items->first()?->options)->toHaveCount(4);
});

test('customer cannot manipulate price', function () {
    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedOrderCustomer();
    ['branch' => $branch, 'product' => $product, 'onion' => $onion, 'cheese' => $cheese] = seedOrderCatalog();

    $payload = validOrderPayload($branch, $product, $onion, $cheese, $address);
    $payload['items'][0]['unit_price'] = 1;
    $payload['total'] = 1;

    $order = app(CreateOrder::class)->handle($customer, $user, $payload);

    expect((string) $order->items->first()?->unit_list_price)->toBe('105.00')
        ->and((float) $order->total)->toBeGreaterThan(100);
});

test('customer cannot mix branches', function () {
    ['user' => $user, 'customer' => $customer, 'address' => $address] = seedOrderCustomer();
    ['branch' => $branch, 'product' => $product, 'onion' => $onion, 'cheese' => $cheese] = seedOrderCatalog();

    $otherBranch = BusinessBranch::factory()->create();
    $otherProduct = Product::factory()->create(['branch_id' => $otherBranch->id, 'is_available' => true, 'is_active' => true]);
    ProductPrice::factory()->create(['product_id' => $otherProduct->id, 'list_price' => 40, 'is_active' => true]);

    $payload = validOrderPayload($branch, $product, $onion, $cheese, $address);
    $payload['items'][] = [
        'product_id' => $otherProduct->id,
        'quantity' => 1,
        'selected_options' => [],
    ];

    expect(fn () => app(CreateOrder::class)->handle($customer, $user, $payload))
        ->toThrow(ValidationException::class);
});

test('business user sees orders from allowed branch', function () {
    ['user' => $customerUser, 'customer' => $customer, 'address' => $address] = seedOrderCustomer();
    ['business' => $business, 'branch' => $branch, 'product' => $product, 'onion' => $onion, 'cheese' => $cheese] = seedOrderCatalog();

    $admin = User::factory()->businessAdmin()->create();
    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ])->branches()->sync([$branch->id]);

    app(CreateOrder::class)->handle(
        $customer,
        $customerUser,
        validOrderPayload($branch, $product, $onion, $cheese, $address),
    );

    $this->actingAs($admin)
        ->get(route('business.orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('business/orders/index')
            ->has('orders.data', 1)
            ->where('filters.from', now()->toDateString())
            ->where('filters.to', now()->toDateString()));
});

test('business orders index lists pending orders before delivered orders', function () {
    ['business' => $business, 'branch' => $branch] = seedOrderCatalog();

    $admin = User::factory()->businessAdmin()->create();
    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ])->branches()->sync([$branch->id]);

    $delivered = Order::factory()->create([
        'branch_id' => $branch->id,
        'order_status' => OrderStatus::Delivered,
    ]);
    $pending = Order::factory()->create([
        'branch_id' => $branch->id,
        'order_status' => OrderStatus::PendingBusiness,
    ]);
    $preparing = Order::factory()->create([
        'branch_id' => $branch->id,
        'order_status' => OrderStatus::Preparing,
    ]);

    $this->actingAs($admin)
        ->get(route('business.orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('orders.data', 3)
            ->where('orders.data.0.order_number', $pending->order_number)
            ->where('orders.data.1.order_number', $preparing->order_number)
            ->where('orders.data.2.order_number', $delivered->order_number));
});

test('business orders index lists cancelled orders after delivered orders', function () {
    ['business' => $business, 'branch' => $branch] = seedOrderCatalog();

    $admin = User::factory()->businessAdmin()->create();
    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ])->branches()->sync([$branch->id]);

    $cancelled = Order::factory()->create([
        'branch_id' => $branch->id,
        'order_status' => OrderStatus::Cancelled,
    ]);
    $delivered = Order::factory()->create([
        'branch_id' => $branch->id,
        'order_status' => OrderStatus::Delivered,
    ]);

    $this->actingAs($admin)
        ->get(route('business.orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('orders.data.0.order_number', $delivered->order_number)
            ->where('orders.data.1.order_number', $cancelled->order_number));
});

test('business orders index keeps pending orders first even when they are older', function () {
    ['business' => $business, 'branch' => $branch] = seedOrderCatalog();

    $admin = User::factory()->businessAdmin()->create();
    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ])->branches()->sync([$branch->id]);

    $pending = Order::factory()->create([
        'branch_id' => $branch->id,
        'order_status' => OrderStatus::PendingBusiness,
    ]);
    $delivered = Order::factory()->create([
        'branch_id' => $branch->id,
        'order_status' => OrderStatus::Delivered,
    ]);

    Order::query()->whereKey($pending->id)->update(['created_at' => now()->subHour()]);
    Order::query()->whereKey($delivered->id)->update(['created_at' => now()]);

    $this->actingAs($admin)
        ->get(route('business.orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('orders.data.0.order_number', $pending->order_number)
            ->where('orders.data.1.order_number', $delivered->order_number));
});

test('business orders index excludes orders outside selected date range', function () {
    ['user' => $customerUser, 'customer' => $customer, 'address' => $address] = seedOrderCustomer();
    ['business' => $business, 'branch' => $branch, 'product' => $product, 'onion' => $onion, 'cheese' => $cheese] = seedOrderCatalog();

    $admin = User::factory()->businessAdmin()->create();
    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ])->branches()->sync([$branch->id]);

    $order = app(CreateOrder::class)->handle(
        $customer,
        $customerUser,
        validOrderPayload($branch, $product, $onion, $cheese, $address),
    );

    Order::query()
        ->whereKey($order->id)
        ->update(['created_at' => now()->subDay()]);

    $this->actingAs($admin)
        ->get(route('business.orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('orders.data', 0));

    $this->actingAs($admin)
        ->get(route('business.orders.index', [
            'from' => now()->subDay()->toDateString(),
            'to' => now()->subDay()->toDateString(),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('orders.data', 1));
});

test('employee cannot see another branch order', function () {
    ['user' => $customerUser, 'customer' => $customer, 'address' => $address] = seedOrderCustomer();
    ['business' => $business, 'branch' => $branch, 'product' => $product, 'onion' => $onion, 'cheese' => $cheese] = seedOrderCatalog();
    $otherBranch = BusinessBranch::factory()->for($business)->create(['name' => 'Otra']);

    $employee = User::factory()->businessEmployee()->create();
    $membership = BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $employee->id,
        'role' => BusinessUserRole::BusinessEmployee,
        'status' => BusinessUserStatus::Active,
    ]);
    $membership->branches()->sync([$otherBranch->id]);

    $order = app(CreateOrder::class)->handle(
        $customer,
        $customerUser,
        validOrderPayload($branch, $product, $onion, $cheese, $address),
    );

    $this->actingAs($employee)
        ->get(route('business.orders.show', $order))
        ->assertNotFound();
});

test('business order detail only exposes customer name phone address and reputation', function () {
    ['user' => $customerUser, 'customer' => $customer, 'address' => $address] = seedOrderCustomer();
    ['business' => $business, 'branch' => $branch, 'product' => $product, 'onion' => $onion, 'cheese' => $cheese] = seedOrderCatalog();

    $admin = User::factory()->businessAdmin()->create();
    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ])->branches()->sync([$branch->id]);

    $order = app(CreateOrder::class)->handle(
        $customer,
        $customerUser,
        validOrderPayload($branch, $product, $onion, $cheese, $address),
    );

    $this->actingAs($admin)
        ->get(route('business.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('business/orders/show')
            ->where('order.customer.name', $customerUser->name)
            ->where('order.customer.phone', $customerUser->phone)
            ->where('order.customer.reputation_label', $customer->trust_level->label())
            ->has('order.customer.reputation_tone')
            ->has('order.delivery_address.address_text')
            ->where('order.driver', null)
            ->missing('order.customer.email')
            ->missing('order.customer.id')
            ->missing('order.delivery_address.latitude')
            ->missing('order.delivery_address.longitude'));
});

test('business order detail shows assigned driver name and phone only', function () {
    ['user' => $customerUser, 'customer' => $customer, 'address' => $address] = seedOrderCustomer();
    ['business' => $business, 'branch' => $branch, 'product' => $product, 'onion' => $onion, 'cheese' => $cheese] = seedOrderCatalog();

    $admin = User::factory()->businessAdmin()->create();
    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ])->branches()->sync([$branch->id]);

    $driverUser = User::factory()->driver()->create([
        'first_name' => 'Pedro',
        'last_name' => 'Repartidor',
        'phone' => '+529611112233',
    ]);
    $driver = Driver::factory()->approved()->forUser($driverUser)->create();

    $order = app(CreateOrder::class)->handle(
        $customer,
        $customerUser,
        validOrderPayload($branch, $product, $onion, $cheese, $address),
    );

    $order->forceFill([
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::DriverAssigned,
    ])->save();

    $this->actingAs($admin)
        ->get(route('business.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('business/orders/show')
            ->where('order.driver.name', $driverUser->name)
            ->where('order.driver.phone', $driverUser->phone)
            ->missing('order.driver.id')
            ->missing('order.driver.email'));
});

test('business can accept pending order requiring preparation time', function () {
    ['user' => $customerUser, 'customer' => $customer, 'address' => $address] = seedOrderCustomer();
    ['business' => $business, 'branch' => $branch, 'product' => $product, 'onion' => $onion, 'cheese' => $cheese] = seedOrderCatalog();

    $admin = User::factory()->businessAdmin()->create();
    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ])->branches()->sync([$branch->id]);

    $order = app(CreateOrder::class)->handle(
        $customer,
        $customerUser,
        validOrderPayload($branch, $product, $onion, $cheese, $address),
    );

    $this->actingAs($admin)
        ->post(route('business.orders.accept', $order), [
            'estimated_preparation_minutes' => 20,
        ])
        ->assertRedirect();

    $order->refresh();

    expect($order->order_status)->toBe(OrderStatus::Preparing)
        ->and($order->estimated_preparation_minutes)->toBe(20)
        ->and($order->business_accepted_at)->not->toBeNull();
});

test('business can mark preparing order ready', function () {
    $order = Order::factory()->create([
        'order_status' => OrderStatus::Preparing,
        'preparation_started_at' => now(),
    ]);
    $actor = User::factory()->businessAdmin()->create();

    $updated = app(MarkOrderReady::class)->handle($order, $actor);

    expect($updated->order_status)->toBe(OrderStatus::ReadyForPickup)
        ->and($updated->ready_at)->not->toBeNull();
});

test('invalid order transition is rejected', function () {
    $order = Order::factory()->create([
        'order_status' => OrderStatus::PendingBusiness,
    ]);

    expect(fn () => app(OrderStateService::class)->transition(
        $order,
        OrderStatus::ReadyForPickup,
    ))->toThrow(ValidationException::class);
});

test('two simultaneous accepts do not corrupt order', function () {
    ['user' => $customerUser, 'customer' => $customer, 'address' => $address] = seedOrderCustomer();
    ['business' => $business, 'branch' => $branch, 'product' => $product, 'onion' => $onion, 'cheese' => $cheese] = seedOrderCatalog();

    $admin = User::factory()->businessAdmin()->create();
    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $admin->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ])->branches()->sync([$branch->id]);

    $order = app(CreateOrder::class)->handle(
        $customer,
        $customerUser,
        validOrderPayload($branch, $product, $onion, $cheese, $address),
    );

    app(AcceptBusinessOrder::class)->handle($order, $admin, 15);

    expect(fn () => app(AcceptBusinessOrder::class)->handle($order->fresh(), $admin, 20))
        ->toThrow(ValidationException::class);

    expect($order->fresh()->order_status)->toBe(OrderStatus::Preparing)
        ->and($order->fresh()->estimated_preparation_minutes)->toBe(15);
});
