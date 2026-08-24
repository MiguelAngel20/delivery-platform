<?php

use App\Enums\UserRole;
use App\Http\Controllers\Web\Business\BranchController;
use App\Http\Controllers\Web\Business\BusinessProfileController;
use App\Http\Controllers\Web\Business\CategoryController;
use App\Http\Controllers\Web\Business\DriverController;
use App\Http\Controllers\Web\Business\EmployeeController;
use App\Http\Controllers\Web\Business\FinanceController;
use App\Http\Controllers\Web\Business\HomeController;
use App\Http\Controllers\Web\Business\OrderController;
use App\Http\Controllers\Web\Business\ProductController;
use App\Http\Controllers\Web\Business\PromotionController;
use App\Http\Controllers\Web\Business\SettingsController;
use App\Http\Controllers\Web\Business\SubcategoryController;
use App\Http\Controllers\Web\Business\UpgradeRequestController;
use App\Http\Controllers\Web\Notifications\NotificationPreferencesController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth',
    'role:'.UserRole::BusinessAdmin->value.','.UserRole::BusinessEmployee->value,
])
    ->prefix('business')
    ->name('business.')
    ->group(function () {
        Route::get('/', HomeController::class)->name('home');

        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/accept', [OrderController::class, 'accept'])->name('orders.accept');
        Route::post('orders/{order}/reject', [OrderController::class, 'reject'])->name('orders.reject');
        Route::post('orders/{order}/ready', [OrderController::class, 'markReady'])->name('orders.ready');
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('orders/{order}/incidents', [OrderController::class, 'reportIncident'])->name('orders.incidents.store');

        Route::middleware('role:'.UserRole::BusinessAdmin->value)->group(function () {
            Route::get('finance', [FinanceController::class, 'index'])->name('finance.index');

            Route::get('products', [ProductController::class, 'index'])->name('products.index');
            Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
            Route::post('products', [ProductController::class, 'store'])->name('products.store');
            Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
            Route::post('products/{product}', [ProductController::class, 'update'])->name('products.update');

            Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
            Route::get('categories/create', [CategoryController::class, 'create'])->name('categories.create');
            Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::get('categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
            Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
            Route::post('categories/{category}/deactivate', [CategoryController::class, 'deactivate'])->name('categories.deactivate');
            Route::post('categories/{category}/activate', [CategoryController::class, 'activate'])->name('categories.activate');

            Route::get('subcategories', [SubcategoryController::class, 'index'])->name('subcategories.index');
            Route::get('subcategories/create', [SubcategoryController::class, 'create'])->name('subcategories.create');
            Route::post('subcategories', [SubcategoryController::class, 'store'])->name('subcategories.store');
            Route::get('subcategories/{subcategory}/edit', [SubcategoryController::class, 'edit'])->name('subcategories.edit');
            Route::put('subcategories/{subcategory}', [SubcategoryController::class, 'update'])->name('subcategories.update');
            Route::post('subcategories/{subcategory}/deactivate', [SubcategoryController::class, 'deactivate'])->name('subcategories.deactivate');
            Route::post('subcategories/{subcategory}/activate', [SubcategoryController::class, 'activate'])->name('subcategories.activate');

            Route::get('promotions', [PromotionController::class, 'index'])->name('promotions.index');
            Route::get('promotions/create', [PromotionController::class, 'create'])->name('promotions.create');
            Route::post('promotions', [PromotionController::class, 'store'])->name('promotions.store');
            Route::get('promotions/{promotion}/edit', [PromotionController::class, 'edit'])->name('promotions.edit');
            Route::post('promotions/{promotion}', [PromotionController::class, 'update'])->name('promotions.update');
            Route::post('promotions/{promotion}/pause', [PromotionController::class, 'pause'])->name('promotions.pause');
            Route::post('promotions/{promotion}/activate', [PromotionController::class, 'activate'])->name('promotions.activate');
            Route::post('promotions/{promotion}/archive', [PromotionController::class, 'archive'])->name('promotions.archive');

            Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
            Route::get('settings/business', [BusinessProfileController::class, 'edit'])->name('settings.business.edit');
            Route::post('settings/business', [BusinessProfileController::class, 'update'])->name('settings.business.update');
            Route::get('settings/branches', [BranchController::class, 'index'])->name('settings.branches.index');
            Route::get('settings/branches/{branch}/edit', [BranchController::class, 'edit'])->name('settings.branches.edit');
            Route::put('settings/branches/{branch}', [BranchController::class, 'update'])->name('settings.branches.update');
            Route::get('settings/notifications', [NotificationPreferencesController::class, 'edit'])
                ->name('settings.notifications.edit');
            Route::put('settings/notifications', [NotificationPreferencesController::class, 'update'])
                ->name('settings.notifications.update');

            Route::get('drivers', [DriverController::class, 'index'])->name('drivers.index');
            Route::get('drivers/create', [DriverController::class, 'create'])->name('drivers.create');
            Route::post('drivers', [DriverController::class, 'store'])->name('drivers.store');
            Route::get('drivers/{driver}/edit', [DriverController::class, 'edit'])->name('drivers.edit');
            Route::put('drivers/{driver}', [DriverController::class, 'update'])->name('drivers.update');
            Route::delete('drivers/{driver}', [DriverController::class, 'destroy'])->name('drivers.destroy');

            Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
            Route::get('employees/create', [EmployeeController::class, 'create'])->name('employees.create');
            Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
            Route::get('employees/{businessUser}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
            Route::put('employees/{businessUser}', [EmployeeController::class, 'update'])->name('employees.update');
            Route::post('employees/{businessUser}/deactivate', [EmployeeController::class, 'deactivate'])
                ->name('employees.deactivate');
            Route::post('employees/{businessUser}/activate', [EmployeeController::class, 'activate'])
                ->name('employees.activate');

            Route::get('upgrade-requests', [UpgradeRequestController::class, 'index'])
                ->name('upgrade-requests.index');
            Route::post('upgrade-requests', [UpgradeRequestController::class, 'store'])
                ->name('upgrade-requests.store');
        });
    });
