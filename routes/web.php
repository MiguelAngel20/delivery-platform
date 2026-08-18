<?php

use App\Http\Controllers\Web\Auth\ForcePasswordChangeController;
use App\Http\Controllers\Web\Auth\LoginPageController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

require __DIR__.'/storefront.php';

Route::middleware('guest')->group(function () {
    Route::get('admin/login', [LoginPageController::class, 'admin'])->name('admin.login');
    Route::get('business/login', [LoginPageController::class, 'business'])->name('business.login');
    Route::get('driver/login', [LoginPageController::class, 'driver'])->name('driver.login');
});

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        /** @var User $user */
        $user = auth()->user();

        return redirect()->to($user->homeRoute());
    })->name('dashboard');

    Route::get('password/cambiar', [ForcePasswordChangeController::class, 'edit'])
        ->name('password.force.edit');
    Route::put('password/cambiar', [ForcePasswordChangeController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.force.update');
});

require __DIR__.'/customer.php';
require __DIR__.'/business.php';
require __DIR__.'/driver.php';
require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/notifications.php';
