<?php

use App\Enums\UserRole;
use App\Http\Controllers\Web\Driver\AvailabilityController;
use App\Http\Controllers\Web\Driver\EarningsController;
use App\Http\Controllers\Web\Driver\HomeController;
use App\Http\Controllers\Web\Driver\LocationController;
use App\Http\Controllers\Web\Driver\OrderController;
use App\Http\Controllers\Web\Driver\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth',
    'role:'.UserRole::Driver->value,
])
    ->prefix('driver')
    ->name('driver.')
    ->group(function () {
        Route::get('/', HomeController::class)->name('home');

        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::post('orders/{order}/accept', [OrderController::class, 'accept'])->name('orders.accept');
        Route::post('orders/{order}/reject', [OrderController::class, 'reject'])->name('orders.reject');
        Route::post('orders/{order}/arrive', [OrderController::class, 'arrive'])->name('orders.arrive');
        Route::post('orders/{order}/pickup', [OrderController::class, 'pickup'])->name('orders.pickup');
        Route::post('orders/{order}/start-delivery', [OrderController::class, 'startDelivery'])->name('orders.start-delivery');
        Route::post('orders/{order}/deliver', [OrderController::class, 'deliver'])->name('orders.deliver');
        Route::post('orders/{order}/cannot-continue', [OrderController::class, 'cannotContinue'])->name('orders.cannot-continue');
        Route::post('orders/{order}/incidents', [OrderController::class, 'reportIncident'])->name('orders.incidents.store');

        Route::patch('availability', [AvailabilityController::class, 'update'])->name('availability.update');
        Route::post('location', [LocationController::class, 'update'])->name('location.update');

        Route::get('earnings', [EarningsController::class, 'index'])->name('earnings.index');
        Route::inertia('history', 'driver/history/index')->name('history.index');
        Route::get('profile', ProfileController::class)->name('profile.index');
        Route::get('profile/notifications', [\App\Http\Controllers\Web\Notifications\NotificationPreferencesController::class, 'edit'])
            ->name('profile.notifications.edit');
        Route::put('profile/notifications', [\App\Http\Controllers\Web\Notifications\NotificationPreferencesController::class, 'update'])
            ->name('profile.notifications.update');
    });
