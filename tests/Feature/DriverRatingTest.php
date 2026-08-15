<?php

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\DriverRating;
use App\Models\Order;
use App\Models\User;
use App\Support\OrderData;

function driverRatingMakeDeliveredOrder(): array
{
    $customerUser = User::factory()->customer()->create();
    $customer = Customer::factory()->forUser($customerUser)->create();
    $driverUser = User::factory()->driver()->create();
    $driver = Driver::factory()->approved()->forUser($driverUser)->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::Delivered,
        'delivered_at' => now(),
    ]);

    return compact('customerUser', 'customer', 'driverUser', 'driver', 'order');
}

test('customer can rate driver after delivered order', function () {
    ['customerUser' => $customerUser, 'driver' => $driver, 'order' => $order] = driverRatingMakeDeliveredOrder();

    $this->actingAs($customerUser)
        ->post(route('customer.orders.ratings.store', $order), [
            'overall_rating' => 5,
            'speed_rating' => 4,
            'comment' => 'Muy puntual.',
        ])
        ->assertRedirect();

    $rating = DriverRating::query()->where('order_id', $order->id)->first();

    expect($rating)->not->toBeNull()
        ->and($rating->driver_id)->toBe($driver->id)
        ->and($rating->overall_rating)->toBe(5)
        ->and($rating->speed_rating)->toBe(4)
        ->and($driver->fresh()->metrics->total_ratings)->toBe(1)
        ->and((float) $driver->fresh()->metrics->average_rating)->toBe(5.0);
});

test('customer cannot rate another driver order', function () {
    ['order' => $order] = driverRatingMakeDeliveredOrder();
    $otherUser = User::factory()->customer()->create();
    Customer::factory()->forUser($otherUser)->create();

    $this->actingAs($otherUser)
        ->post(route('customer.orders.ratings.store', $order), [
            'overall_rating' => 5,
        ])
        ->assertForbidden();

    expect(DriverRating::query()->where('order_id', $order->id)->exists())->toBeFalse();
});

test('customer cannot rate cancelled order', function () {
    ['customerUser' => $customerUser, 'order' => $order] = driverRatingMakeDeliveredOrder();
    $order->forceFill(['order_status' => OrderStatus::Cancelled])->save();

    $this->actingAs($customerUser)
        ->post(route('customer.orders.ratings.store', $order->fresh()), [
            'overall_rating' => 5,
        ])
        ->assertForbidden();

    expect(DriverRating::query()->where('order_id', $order->id)->exists())->toBeFalse();
});

test('duplicate rating is rejected', function () {
    ['customerUser' => $customerUser, 'order' => $order] = driverRatingMakeDeliveredOrder();

    $this->actingAs($customerUser)
        ->post(route('customer.orders.ratings.store', $order), [
            'overall_rating' => 5,
        ])
        ->assertRedirect();

    $this->actingAs($customerUser)
        ->post(route('customer.orders.ratings.store', $order), [
            'overall_rating' => 1,
        ])
        ->assertForbidden();

    expect(DriverRating::query()->where('order_id', $order->id)->count())->toBe(1);
});

test('driver average recalculates correctly', function () {
    $driverUser = User::factory()->driver()->create();
    $driver = Driver::factory()->approved()->forUser($driverUser)->create();

    $first = driverRatingMakeDeliveredOrder();
    $first['order']->forceFill(['assigned_driver_id' => $driver->id])->save();

    $secondCustomerUser = User::factory()->customer()->create();
    $secondCustomer = Customer::factory()->forUser($secondCustomerUser)->create();
    $secondOrder = Order::factory()->create([
        'customer_id' => $secondCustomer->id,
        'assigned_driver_id' => $driver->id,
        'order_status' => OrderStatus::Delivered,
        'delivered_at' => now(),
    ]);

    $this->actingAs($first['customerUser'])
        ->post(route('customer.orders.ratings.store', $first['order']->fresh()), [
            'overall_rating' => 5,
        ])
        ->assertRedirect();

    $this->actingAs($secondCustomerUser)
        ->post(route('customer.orders.ratings.store', $secondOrder), [
            'overall_rating' => 3,
        ])
        ->assertRedirect();

    $metrics = $driver->fresh()->metrics;

    expect($metrics->total_ratings)->toBe(2)
        ->and((float) $metrics->average_rating)->toBe(4.0);
});

test('driver active card hides sensitive customer data', function () {
    ['order' => $order] = driverRatingMakeDeliveredOrder();

    $card = OrderData::driverActiveCard($order->fresh(['customer.user', 'customer.metrics']));

    expect($card['customer'])->not->toHaveKey('phone')
        ->and($card['customer'])->not->toHaveKey('email')
        ->and($card['customer']['name'])->not->toContain('@');
});
