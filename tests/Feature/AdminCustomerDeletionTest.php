<?php

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerMetric;
use App\Models\CustomOrderRequest;
use App\Models\NotificationPreference;
use App\Models\Order;
use App\Models\User;

test('admin deletes a customer with no orders and frees the phone', function () {
    $admin = User::factory()->systemAdmin()->create();
    $customer = Customer::factory()->create();
    $user = $customer->user;
    $phone = $user->phone;

    CustomerAddress::factory()->create(['customer_id' => $customer->id]);
    CustomerMetric::factory()->create(['customer_id' => $customer->id]);
    NotificationPreference::factory()->create(['user_id' => $user->id]);

    $this->actingAs($admin)
        ->delete(route('admin.customers.destroy', $customer))
        ->assertRedirect(route('admin.customers.index'));

    expect(Customer::query()->find($customer->id))->toBeNull()
        ->and(User::withTrashed()->find($user->id))->toBeNull()
        ->and(CustomerAddress::withTrashed()->where('customer_id', $customer->id)->exists())->toBeFalse();

    $replacement = User::factory()->customer()->create(['phone' => $phone]);

    expect($replacement->phone)->toBe($phone);
});

test('a cancelled order keeps the customer out of the table delete and preserves history', function () {
    $admin = User::factory()->systemAdmin()->create();
    $withoutOrders = Customer::factory()->create();
    $withOrders = Customer::factory()->create();
    $user = $withOrders->user;
    $phone = $user->phone;
    $name = $user->name;

    $order = Order::factory()->create([
        'customer_id' => $withOrders->id,
        'order_status' => OrderStatus::Cancelled,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.customers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('customers.data', function (mixed $rows) use ($withoutOrders, $withOrders): bool {
                $indexed = collect($rows)->keyBy('id');

                return $indexed[$withoutOrders->id]['has_order_history'] === false
                    && $indexed[$withOrders->id]['has_order_history'] === true;
            }));

    $this->actingAs($admin)
        ->get(route('admin.customers.show', $withOrders))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('customer.has_order_history', true));

    $this->actingAs($admin)
        ->delete(route('admin.customers.destroy', $withOrders))
        ->assertRedirect(route('admin.customers.index'));

    $retired = User::withTrashed()->find($user->id);

    expect($retired)->not->toBeNull()
        ->and($retired->trashed())->toBeTrue()
        ->and($retired->name)->toBe($name)
        ->and($retired->phone)->not->toBe($phone)
        ->and(Customer::query()->find($withOrders->id))->not->toBeNull()
        ->and(Order::query()->find($order->id))->not->toBeNull();

    $order->load('customer.user');

    expect($order->customer->user->name)->toBe($name);

    $this->actingAs($admin)
        ->get(route('admin.customers.show', $withOrders))
        ->assertNotFound();

    $this->actingAs($admin)
        ->get(route('admin.customers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('customers.data', function (mixed $rows) use ($withOrders, $withoutOrders): bool {
                $ids = collect($rows)->pluck('id');

                return $ids->contains($withoutOrders->id)
                    && $ids->doesntContain($withOrders->id);
            }));

    User::factory()->customer()->create(['phone' => $phone]);
});

test('a custom order counts as order history', function () {
    $admin = User::factory()->systemAdmin()->create();
    $customer = Customer::factory()->create();

    CustomOrderRequest::factory()->create([
        'customer_id' => $customer->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.customers.show', $customer))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('customer.has_order_history', true));

    $this->actingAs($admin)
        ->delete(route('admin.customers.destroy', $customer))
        ->assertRedirect(route('admin.customers.index'));

    expect(Customer::query()->find($customer->id))->not->toBeNull()
        ->and(CustomOrderRequest::query()->where('customer_id', $customer->id)->exists())->toBeTrue()
        ->and($customer->user()->first()?->trashed())->toBeTrue();
});

test('admin customer list shows phone and email verification', function () {
    $admin = User::factory()->systemAdmin()->create();
    $verified = Customer::factory()->create();
    $unverified = Customer::factory()->create();

    $verified->user->forceFill([
        'email' => 'cliente@example.com',
        'email_verified_at' => now(),
        'phone_verified_at' => now(),
    ])->save();

    $unverified->user->forceFill([
        'email' => null,
        'email_verified_at' => null,
        'phone_verified_at' => null,
    ])->save();

    $this->actingAs($admin)
        ->get(route('admin.customers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('customers.data', function (mixed $rows) use ($verified, $unverified): bool {
                $indexed = collect($rows)->keyBy('id');

                return $indexed[$verified->id]['phone_verified'] === true
                    && $indexed[$verified->id]['email_verified'] === true
                    && $indexed[$verified->id]['has_email'] === true
                    && $indexed[$unverified->id]['phone_verified'] === false
                    && $indexed[$unverified->id]['email_verified'] === false
                    && $indexed[$unverified->id]['has_email'] === false;
            }));
});

test('a customer cannot delete accounts from the admin', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($customer->user)
        ->delete(route('admin.customers.destroy', $customer))
        ->assertForbidden();

    expect(Customer::query()->find($customer->id))->not->toBeNull();
});
