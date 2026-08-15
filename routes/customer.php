<?php

use App\Enums\UserRole;
use App\Http\Controllers\Web\Customer\AddressController;
use App\Http\Controllers\Web\Customer\CheckoutController;
use App\Http\Controllers\Web\Customer\CustomOrderController;
use App\Http\Controllers\Web\Customer\DriverRatingController;
use App\Http\Controllers\Web\Customer\OrderController;
use App\Http\Controllers\Web\Customer\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth',
    'role:'.UserRole::Customer->value,
])
    ->prefix('customer')
    ->name('customer.')
    ->group(function () {
        Route::get('/', fn () => redirect()->route('home'))->name('home');

        Route::get('checkout', CheckoutController::class)->name('checkout');
        Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('orders/{order}/incidents', [OrderController::class, 'reportIncident'])->name('orders.incidents.store');
        Route::post('orders/{order}/ratings', [DriverRatingController::class, 'store'])->name('orders.ratings.store');
        Route::post('orders/{order}/quotes/accept', [OrderController::class, 'acceptQuote'])->name('orders.quotes.accept');

        Route::get('custom-orders', [CustomOrderController::class, 'index'])->name('custom-orders.index');
        Route::get('custom-orders/create', [CustomOrderController::class, 'create'])->name('custom-orders.create');
        Route::post('custom-orders', [CustomOrderController::class, 'store'])->name('custom-orders.store');
        Route::get('custom-orders/{customOrder}', [CustomOrderController::class, 'show'])->name('custom-orders.show');
        Route::post('custom-orders/{customOrder}/accept', [CustomOrderController::class, 'acceptQuote'])->name('custom-orders.accept');
        Route::post('custom-orders/{customOrder}/reject', [CustomOrderController::class, 'rejectQuote'])->name('custom-orders.reject');
        Route::post('custom-orders/{customOrder}/cancel', [CustomOrderController::class, 'cancel'])->name('custom-orders.cancel');

        Route::get('addresses', [AddressController::class, 'index'])->name('addresses.index');
        Route::post('addresses', [AddressController::class, 'store'])->name('addresses.store');
        Route::delete('addresses/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');

        Route::get('profile', ProfileController::class)->name('profile.index');
        Route::get('profile/notifications', [\App\Http\Controllers\Web\Notifications\NotificationPreferencesController::class, 'edit'])
            ->name('profile.notifications.edit');
        Route::put('profile/notifications', [\App\Http\Controllers\Web\Notifications\NotificationPreferencesController::class, 'update'])
            ->name('profile.notifications.update');
    });
