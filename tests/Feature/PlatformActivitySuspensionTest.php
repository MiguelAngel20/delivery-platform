<?php

use App\Actions\Orders\CreateOrder;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Enums\OrderAddressSource;
use App\Enums\OrderStatus;
use App\Enums\PlatformSuspensionReason;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PlatformActivitySuspension;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use App\Services\Platform\PlatformActivitySuspensionService;
use App\Support\BusinessHours;
use Illuminate\Validation\ValidationException;

test('admin can activate and deactivate activity suspension', function () {
    PlatformActivitySuspension::query()->delete();
    PlatformActivitySuspension::factory()->create();

    $admin = User::factory()->systemAdmin()->create();

    $this->actingAs($admin)
        ->put(route('admin.settings.activity-suspension.update'), [
            'is_active' => true,
            'reason' => PlatformSuspensionReason::Rain->value,
        ])
        ->assertRedirect();

    $settings = PlatformActivitySuspension::current();

    expect($settings->is_active)->toBeTrue()
        ->and($settings->reason)->toBe(PlatformSuspensionReason::Rain);

    $this->actingAs($admin)
        ->put(route('admin.settings.activity-suspension.update'), [
            'is_active' => false,
            'reason' => PlatformSuspensionReason::Rain->value,
        ])
        ->assertRedirect();

    expect(PlatformActivitySuspension::current()->fresh()->is_active)->toBeFalse();
});

test('create order is blocked while platform activity is suspended', function () {
    PlatformActivitySuspension::query()->delete();
    PlatformActivitySuspension::factory()->active(PlatformSuspensionReason::Maintenance)->create();
    app(PlatformActivitySuspensionService::class)->forgetCache();

    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->forUser($user)->create();
    $business = Business::factory()->create([
        'status' => BusinessStatus::Active,
        'operation_mode' => BusinessOperationMode::Partner,
    ]);
    $branch = BusinessBranch::factory()->for($business)->create([
        'latitude' => 16.2514,
        'longitude' => -92.1342,
        'opening_hours' => BusinessHours::defaults(),
    ]);
    $product = Product::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
    ProductPrice::factory()->create(['product_id' => $product->id, 'list_price' => 50]);

    expect(fn () => app(CreateOrder::class)->handle($customer, $user, [
        'branch_id' => $branch->id,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1, 'selected_options' => []],
        ],
        'delivery' => [
            'source' => OrderAddressSource::Temporary->value,
            'address_text' => 'Centro',
            'latitude' => 16.2514,
            'longitude' => -92.1342,
        ],
    ]))->toThrow(ValidationException::class);
});

test('storefront shared props include suspension details and active orders', function () {
    PlatformActivitySuspension::query()->delete();
    PlatformActivitySuspension::factory()->active(PlatformSuspensionReason::OffHours)->create();
    app(PlatformActivitySuspensionService::class)->forgetCache();

    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->forUser($user)->create();

    $business = Business::factory()->create(['status' => BusinessStatus::Active]);
    $branch = BusinessBranch::factory()->for($business)->create();

    Order::factory()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'order_status' => OrderStatus::Preparing,
        'order_number' => 'CD-TEST-1',
    ]);

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activitySuspension.active', true)
            ->where('activitySuspension.reason', PlatformSuspensionReason::OffHours->value)
            ->where('activitySuspension.title', PlatformSuspensionReason::OffHours->title())
            ->has('activitySuspension.active_orders', 1)
            ->where('activitySuspension.active_orders.0.status_label', OrderStatus::Preparing->customerLabel())
            ->where('activitySuspension.active_order_message', PlatformSuspensionReason::OffHours->activeOrderMessage()));
});

test('admin activity suspension page is available', function () {
    PlatformActivitySuspension::query()->delete();
    PlatformActivitySuspension::factory()->create();

    $admin = User::factory()->systemAdmin()->create();

    $this->actingAs($admin)
        ->get(route('admin.settings.activity-suspension.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/settings/activity-suspension')
            ->has('reasons', 3)
            ->has('suspension.is_active'));
});
