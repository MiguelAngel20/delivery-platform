<?php

use App\Http\Controllers\Api\V1\Notifications\NotificationInboxController;
use App\Http\Controllers\Api\V1\Notifications\PushDeviceController;
use App\Http\Controllers\Web\Notifications\NotificationPreferencesController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::post('push/devices', [PushDeviceController::class, 'store'])
        ->name('push.devices.store');
    Route::delete('push/devices', [PushDeviceController::class, 'destroy'])
        ->name('push.devices.destroy');

    Route::get('notifications/inbox', [NotificationInboxController::class, 'index'])
        ->name('notifications.inbox');
    Route::post('notifications/read-all', [NotificationInboxController::class, 'markAllAsRead'])
        ->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationInboxController::class, 'markAsRead'])
        ->name('notifications.read');
});
