<?php

use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Enums\OptionSelectionAction;
use App\Enums\OrderStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\BusinessUser;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use App\Models\ProductPrice;
use App\Models\User;
use App\Notifications\Auth\CustomerEmailVerificationCode;
use App\Notifications\Orders\NewBusinessOrderNotification;
use App\Notifications\Orders\PlatformOrderPendingNotification;
use Illuminate\Support\Facades\Notification;

/**
 * @return array{business: Business, branch: BusinessBranch, product: Product, onion: ProductOption, cheese: ProductOption}
 */
function journeyPartnerCatalog(): array
{
    $business = Business::factory()->create([
        'name' => 'Taquería Journey',
        'operation_mode' => BusinessOperationMode::Partner,
        'status' => BusinessStatus::Active,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create([
        'name' => 'Centro',
        'latitude' => 16.2512,
        'longitude' => -92.1342,
    ]);
    $product = Product::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Taco al pastor',
        'is_active' => true,
        'is_available' => true,
        'allow_special_instructions' => true,
    ]);
    ProductPrice::factory()->create([
        'product_id' => $product->id,
        'list_price' => 45,
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
        'max_selection' => 5,
    ]);
    $cheese = ProductOption::factory()->create([
        'option_group_id' => $addon->id,
        'name' => 'Queso',
        'price_modifier' => 10,
    ]);

    return compact('business', 'branch', 'product', 'onion', 'cheese');
}

/**
 * @return array{business: Business, branch: BusinessBranch, product: Product}
 */
function journeyPlatformCatalog(): array
{
    $business = Business::factory()->create([
        'name' => 'ChisDrive Market',
        'operation_mode' => BusinessOperationMode::PlatformOperated,
        'status' => BusinessStatus::Active,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create([
        'name' => 'Bodega',
        'latitude' => 16.2512,
        'longitude' => -92.1342,
    ]);
    $product = Product::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Combo familiar',
        'is_active' => true,
        'is_available' => true,
        'allow_special_instructions' => true,
    ]);
    ProductPrice::factory()->create([
        'product_id' => $product->id,
        'list_price' => 150,
        'acquisition_cost' => 120,
        'is_active' => true,
    ]);

    return compact('business', 'branch', 'product');
}

/**
 * @return array{user: User, address: CustomerAddress}
 */
function journeyRegisterAndVerify(mixed $test, string $email, string $phoneNational = '9611234567'): array
{
    Notification::fake();

    $test->post(route('register.store'), [
        'first_name' => 'María',
        'last_name' => 'García',
        'email' => $email,
        'phone_dial_code' => '+52',
        'phone_national' => $phoneNational,
        'password' => 'Clave123!',
        'password_confirmation' => 'Clave123!',
        'address_label' => 'Casa',
        'address_text' => 'Calle Central 12, Comitán',
        'formatted_address' => 'Calle Central 12, Comitán de Domínguez, Chiapas',
        'reference' => 'Portón verde',
        'latitude' => 16.2512,
        'longitude' => -92.1342,
        'place_id' => 'ChIJjourney',
        'google_maps_url' => null,
    ])->assertRedirect(route('register.verify-email'));

    $user = User::query()->where('email', $email)->firstOrFail();
    $code = '';

    Notification::assertSentTo(
        $user,
        CustomerEmailVerificationCode::class,
        function (CustomerEmailVerificationCode $notification) use (&$code): bool {
            $code = $notification->code;

            return true;
        },
    );

    $test->post(route('register.verify-email.store'), ['code' => $code])
        ->assertRedirect(route('cart'));

    $test->assertAuthenticatedAs($user);

    $address = $user->customer?->addresses()->where('is_default', true)->firstOrFail();

    return ['user' => $user->fresh(), 'address' => $address];
}

test('registered customer can order from partner and business sees the requested items', function () {
    [
        'business' => $business,
        'branch' => $branch,
        'product' => $product,
        'onion' => $onion,
        'cheese' => $cheese,
    ] = journeyPartnerCatalog();

    $businessAdmin = User::factory()->businessAdmin()->create();
    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $businessAdmin->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ])->branches()->sync([$branch->id]);

    ['user' => $customerUser, 'address' => $address] = journeyRegisterAndVerify($this, 'cliente.partner@example.com');

    Notification::fake();

    $this->actingAs($customerUser)
        ->post(route('customer.orders.store'), [
            'branch_id' => $branch->id,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'special_instructions' => 'Sin picante',
                'selected_options' => [
                    [
                        'option_id' => $onion->id,
                        'action' => OptionSelectionAction::Removed->value,
                    ],
                    [
                        'option_id' => $cheese->id,
                        'action' => OptionSelectionAction::Added->value,
                        'quantity' => 2,
                    ],
                ],
            ]],
            'delivery' => [
                'source' => 'saved_address',
                'customer_address_id' => $address->id,
            ],
        ])
        ->assertRedirect();

    $order = Order::query()->where('customer_id', $customerUser->customer->id)->first();

    expect($order)->not->toBeNull()
        ->and($order?->order_status)->toBe(OrderStatus::PendingBusiness)
        ->and($order?->branch_id)->toBe($branch->id);

    Notification::assertSentTo($businessAdmin, NewBusinessOrderNotification::class);

    $this->actingAs($customerUser)
        ->get(route('customer.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('customer/orders/show')
            ->where('order.order_number', $order->order_number)
            ->where('order.items.0.display_name', 'Taco al pastor')
            ->where('order.items.0.quantity', '2.00')
            ->where('order.items.0.notes', 'Sin picante')
            ->where('order.delivery_address.address_text', 'Calle Central 12, Comitán'));

    $this->actingAs($businessAdmin)
        ->get(route('business.orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('business/orders/index')
            ->where('newCount', 1)
            ->has('orders.data', 1)
            ->where('orders.data.0.order_number', $order->order_number));

    $this->actingAs($businessAdmin)
        ->get(route('business.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('business/orders/show')
            ->where('order.order_status', OrderStatus::Accepted->value)
            ->where('order.customer.name', 'María García')
            ->where('order.customer.phone', '+529611234567')
            ->where('order.delivery_address.address_text', 'Calle Central 12, Comitán')
            ->where('order.items.0.display_name', 'Taco al pastor')
            ->where('order.items.0.quantity', '2.00')
            ->where('order.items.0.notes', 'Sin picante')
            ->where('order.items.0.options.0.display', 'SIN CEBOLLA')
            ->where('order.items.0.options.1.display', '2 QUESO')
            ->where('order.actions.business_can_accept', true));
});

test('registered customer can order from platform business and admin sees the requested items', function () {
    ['branch' => $branch, 'product' => $product] = journeyPlatformCatalog();
    $admin = User::factory()->systemAdmin()->create();

    ['user' => $customerUser, 'address' => $address] = journeyRegisterAndVerify(
        $this,
        'cliente.platform@example.com',
        '9617654321',
    );

    Notification::fake();

    $this->actingAs($customerUser)
        ->post(route('customer.orders.store'), [
            'branch_id' => $branch->id,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'special_instructions' => 'Entregar en recepción',
                'selected_options' => [],
            ]],
            'delivery' => [
                'source' => 'saved_address',
                'customer_address_id' => $address->id,
            ],
        ])
        ->assertRedirect();

    $order = Order::query()->where('customer_id', $customerUser->customer->id)->first();

    expect($order)->not->toBeNull()
        ->and($order?->order_status)->toBe(OrderStatus::PendingPlatform)
        ->and($order?->isPlatformManaged())->toBeTrue();

    Notification::assertSentTo($admin, PlatformOrderPendingNotification::class);

    $this->actingAs($admin)
        ->get(route('admin.orders.index', ['filter' => 'pending']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/orders/index')
            ->where('queue.pending_platform', 1)
            ->has('orders.data', 1)
            ->where('orders.data.0.order_number', $order->order_number));

    $this->actingAs($admin)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/orders/show')
            ->where('order.order_status', OrderStatus::Accepted->value)
            ->where('order.customer.name', 'María García')
            ->where('order.items.0.display_name', 'Combo familiar')
            ->where('order.items.0.quantity', '1.00')
            ->where('order.items.0.notes', 'Entregar en recepción')
            ->where('order.delivery_address.address_text', 'Calle Central 12, Comitán')
            ->where('order.actions.admin_can_confirm', true));
});
