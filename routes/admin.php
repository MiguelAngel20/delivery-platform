<?php

use App\Enums\UserRole;
use App\Http\Controllers\Web\Admin\BusinessBranchController;
use App\Http\Controllers\Web\Admin\BusinessController;
use App\Http\Controllers\Web\Admin\BusinessLimitController;
use App\Http\Controllers\Web\Admin\BusinessUpgradeRequestController;
use App\Http\Controllers\Web\Admin\BusinessUserController;
use App\Http\Controllers\Web\Admin\Catalog\CatalogController;
use App\Http\Controllers\Web\Admin\CoverageZoneController;
use App\Http\Controllers\Web\Admin\CustomerController;
use App\Http\Controllers\Web\Admin\CustomOrderController;
use App\Http\Controllers\Web\Admin\DriverController;
use App\Http\Controllers\Web\Admin\FinanceController;
use App\Http\Controllers\Web\Admin\HomeController;
use App\Http\Controllers\Web\Admin\IncidentController;
use App\Http\Controllers\Web\Admin\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth',
    'role:'.UserRole::SystemAdmin->value,
])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', HomeController::class)->name('home');

        Route::resource('businesses', BusinessController::class)
            ->except(['destroy']);

        Route::post('businesses/{business}/approve', [BusinessController::class, 'approve'])
            ->name('businesses.approve');
        Route::post('businesses/{business}/reject', [BusinessController::class, 'reject'])
            ->name('businesses.reject');
        Route::post('businesses/{business}/suspend', [BusinessController::class, 'suspend'])
            ->name('businesses.suspend');
        Route::post('businesses/{business}/activate', [BusinessController::class, 'activate'])
            ->name('businesses.activate');

        Route::post('businesses/{business}/branches', [BusinessBranchController::class, 'store'])
            ->name('businesses.branches.store');
        Route::put('businesses/{business}/branches/{branch}', [BusinessBranchController::class, 'update'])
            ->name('businesses.branches.update')
            ->scopeBindings();
        Route::post('businesses/{business}/branches/{branch}/deactivate', [BusinessBranchController::class, 'deactivate'])
            ->name('businesses.branches.deactivate')
            ->scopeBindings();
        Route::post('businesses/{business}/branches/{branch}/activate', [BusinessBranchController::class, 'activate'])
            ->name('businesses.branches.activate')
            ->scopeBindings();

        Route::get('businesses/{business}/users', [BusinessUserController::class, 'index'])
            ->name('businesses.users.index');
        Route::get('businesses/{business}/users/create', [BusinessUserController::class, 'create'])
            ->name('businesses.users.create');
        Route::post('businesses/{business}/users', [BusinessUserController::class, 'store'])
            ->name('businesses.users.store');
        Route::get('businesses/{business}/users/{businessUser}/edit', [BusinessUserController::class, 'edit'])
            ->name('businesses.users.edit');
        Route::put('businesses/{business}/users/{businessUser}', [BusinessUserController::class, 'update'])
            ->name('businesses.users.update');
        Route::post('businesses/{business}/users/{businessUser}/deactivate', [BusinessUserController::class, 'deactivate'])
            ->name('businesses.users.deactivate');
        Route::post('businesses/{business}/users/{businessUser}/activate', [BusinessUserController::class, 'activate'])
            ->name('businesses.users.activate');

        Route::put('businesses/{business}/limits', [BusinessLimitController::class, 'update'])
            ->name('businesses.limits.update');
        Route::post('businesses/{business}/upgrade-requests/{upgradeRequest}/approve', [BusinessUpgradeRequestController::class, 'approve'])
            ->name('businesses.upgrade-requests.approve');
        Route::post('businesses/{business}/upgrade-requests/{upgradeRequest}/reject', [BusinessUpgradeRequestController::class, 'reject'])
            ->name('businesses.upgrade-requests.reject');

        Route::prefix('businesses/{business}/catalog')->name('businesses.catalog.')->group(function () {
            Route::get('/', [CatalogController::class, 'index'])->name('index');

            Route::get('categories', [CatalogController::class, 'categoriesIndex'])->name('categories.index');
            Route::post('categories', [CatalogController::class, 'categoriesStore'])->name('categories.store');

            Route::get('products', [CatalogController::class, 'productsIndex'])->name('products.index');
            Route::get('products/create', [CatalogController::class, 'productsCreate'])->name('products.create');
            Route::post('products', [CatalogController::class, 'productsStore'])->name('products.store');
            Route::get('products/{product}/edit', [CatalogController::class, 'productsEdit'])->name('products.edit');
            Route::post('products/{product}', [CatalogController::class, 'productsUpdate'])->name('products.update');

            Route::get('promotions', [CatalogController::class, 'promotionsIndex'])->name('promotions.index');
            Route::get('promotions/create', [CatalogController::class, 'promotionsCreate'])->name('promotions.create');
            Route::post('promotions', [CatalogController::class, 'promotionsStore'])->name('promotions.store');
            Route::get('promotions/{promotion}/edit', [CatalogController::class, 'promotionsEdit'])->name('promotions.edit');
            Route::post('promotions/{promotion}', [CatalogController::class, 'promotionsUpdate'])->name('promotions.update');
        });

        Route::get('drivers', [DriverController::class, 'index'])->name('drivers.index');
        Route::get('drivers/{driver}', [DriverController::class, 'show'])->name('drivers.show');
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::post('customers/{customer}/block-trust', [CustomerController::class, 'blockTrust'])->name('customers.block-trust');
        Route::post('customers/{customer}/unblock-trust', [CustomerController::class, 'unblockTrust'])->name('customers.unblock-trust');

        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/confirm', [OrderController::class, 'confirm'])->name('orders.confirm');
        Route::post('orders/{order}/reject', [OrderController::class, 'reject'])->name('orders.reject');
        Route::post('orders/{order}/ready', [OrderController::class, 'markReady'])->name('orders.ready');
        Route::post('orders/{order}/quotes', [OrderController::class, 'proposeQuote'])->name('orders.quotes.store');

        Route::get('custom-orders', [CustomOrderController::class, 'index'])->name('custom-orders.index');
        Route::get('custom-orders/{customOrder}', [CustomOrderController::class, 'show'])->name('custom-orders.show');
        Route::post('custom-orders/{customOrder}/claim', [CustomOrderController::class, 'claim'])->name('custom-orders.claim');
        Route::post('custom-orders/{customOrder}/quote', [CustomOrderController::class, 'quote'])->name('custom-orders.quote');
        Route::post('custom-orders/{customOrder}/pickup', [CustomOrderController::class, 'updatePickup'])->name('custom-orders.pickup');
        Route::post('custom-orders/{customOrder}/reject', [CustomOrderController::class, 'reject'])->name('custom-orders.reject');
        Route::get('finance', [FinanceController::class, 'index'])->name('finance.index');
        Route::get('finance/{order}', [FinanceController::class, 'show'])->name('finance.show');
        Route::get('coverage', [CoverageZoneController::class, 'index'])->name('coverage.index');
        Route::post('coverage', [CoverageZoneController::class, 'store'])->name('coverage.store');
        Route::put('coverage/{coverage}', [CoverageZoneController::class, 'update'])->name('coverage.update');
        Route::post('coverage/{coverage}/deactivate', [CoverageZoneController::class, 'deactivate'])->name('coverage.deactivate');
        Route::post('coverage/{coverage}/activate', [CoverageZoneController::class, 'activate'])->name('coverage.activate');
        Route::get('incidents', [IncidentController::class, 'index'])->name('incidents.index');
        Route::get('incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
        Route::post('incidents/{incident}/resolve', [IncidentController::class, 'resolve'])->name('incidents.resolve');
        Route::post('cancellations/{cancellation}/review', [IncidentController::class, 'reviewCancellation'])
            ->name('cancellations.review');
        Route::post('orders/{order}/cancel', [IncidentController::class, 'cancelOrder'])->name('orders.cancel');
        Route::inertia('promotions', 'admin/promotions/index')->name('promotions.index');
        Route::inertia('reports', 'admin/reports/index')->name('reports.index');
        Route::inertia('settings', 'admin/settings/index')->name('settings.index');
        Route::get('settings/notifications', [\App\Http\Controllers\Web\Notifications\NotificationPreferencesController::class, 'edit'])
            ->name('settings.notifications.edit');
        Route::put('settings/notifications', [\App\Http\Controllers\Web\Notifications\NotificationPreferencesController::class, 'update'])
            ->name('settings.notifications.update');
    });
