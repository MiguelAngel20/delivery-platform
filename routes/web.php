<?php

use App\Http\Controllers\Web\Auth\DriverEmailVerificationController;
use App\Http\Controllers\Web\Auth\ForcePasswordChangeController;
use App\Http\Controllers\Web\Auth\LoginPageController;
use App\Models\User;
use App\Support\Portal;
use Illuminate\Support\Facades\Route;

/*
| Shared routes (available on every host when portals-by-host is enabled).
*/
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

require __DIR__.'/settings.php';
require __DIR__.'/notifications.php';

/*
| Storefront / customers
*/
Portal::routes(Portal::STOREFRONT, function () {
    require __DIR__.'/storefront.php';
    require __DIR__.'/customer.php';

    if (Portal::enabled()) {
        foreach ([Portal::ADMIN, Portal::BUSINESS, Portal::DRIVER] as $portal) {
            Route::any($portal.'/{path?}', function () use ($portal) {
                return redirect()->away(
                    Portal::absolute($portal, request()->getRequestUri()),
                );
            })->where('path', '.*');
        }
    }
});

/*
| Admin portal
*/
Portal::routes(Portal::ADMIN, function () {
    Route::middleware('guest')->group(function () {
        Route::get('admin/login', [LoginPageController::class, 'admin'])->name('admin.login');
    });

    require __DIR__.'/admin.php';

    if (Portal::enabled()) {
        Route::redirect('/', '/admin');
    }
});

/*
| Business portal
*/
Portal::routes(Portal::BUSINESS, function () {
    Route::middleware('guest')->group(function () {
        Route::get('business/login', [LoginPageController::class, 'business'])->name('business.login');
    });

    require __DIR__.'/business.php';

    if (Portal::enabled()) {
        Route::redirect('/', '/business');
    }
});

/*
| Driver portal
*/
Portal::routes(Portal::DRIVER, function () {
    Route::middleware('guest')->group(function () {
        Route::get('driver/login', [LoginPageController::class, 'driver'])->name('driver.login');
        Route::get('driver/verificar-correo/{id}/{hash}', DriverEmailVerificationController::class)
            ->name('driver.verification.verify');
    });

    require __DIR__.'/driver.php';

    if (Portal::enabled()) {
        Route::redirect('/', '/driver');
    }
});
