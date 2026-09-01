<?php

use App\Http\Controllers\Web\Auth\CustomerEmailVerificationController;
use App\Http\Controllers\Web\Auth\CustomerRegisterController;
use App\Http\Controllers\Web\Public\CartController;
use App\Http\Controllers\Web\Public\HomeController;
use App\Http\Controllers\Web\Public\LegalPageController;
use App\Http\Controllers\Web\Public\PromotionController;
use App\Http\Controllers\Web\Public\RestaurantController;
use App\Http\Controllers\Web\Public\SearchController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', HomeController::class)->name('home');

Route::get('restaurants', [RestaurantController::class, 'index'])->name('restaurants.index');
Route::get('restaurants/{slug}', [RestaurantController::class, 'show'])->name('restaurants.show');

Route::get('categories', fn () => Inertia::render('public/categories/index'))
    ->name('categories.index');

Route::get('promotions', PromotionController::class)->name('promotions.index');

Route::get('search', SearchController::class)->name('search');

Route::get('cart', fn () => Inertia::render('public/cart/index'))
    ->name('cart');

Route::get('cart/products/{product}', [CartController::class, 'product'])
    ->name('cart.products.show');

Route::get('cart/promotions/{promotion}', [CartController::class, 'promotion'])
    ->name('cart.promotions.show');

Route::middleware('guest')->group(function () {
    Route::get('registro', [CustomerRegisterController::class, 'create'])
        ->name('register');
    Route::post('registro', [CustomerRegisterController::class, 'store'])
        ->middleware('throttle:customer-register')
        ->name('register.store');
    Route::get('registro/verificar-correo', [CustomerEmailVerificationController::class, 'show'])
        ->name('register.verify-email');
    Route::post('registro/verificar-correo', [CustomerEmailVerificationController::class, 'store'])
        ->middleware('throttle:customer-verify-email')
        ->name('register.verify-email.store');
    Route::post('registro/verificar-correo/reenviar', [CustomerEmailVerificationController::class, 'resend'])
        ->middleware('throttle:customer-verify-email')
        ->name('register.verify-email.resend');
});

Route::prefix('legal')->name('legal.')->group(function () {
    Route::get('terminos-y-condiciones', [LegalPageController::class, 'terms'])->name('terms');
    Route::get('aviso-de-privacidad', [LegalPageController::class, 'privacy'])->name('privacy');
    Route::get('quejas-y-sugerencias', [LegalPageController::class, 'feedback'])->name('feedback');
    Route::get('afiliacion', [LegalPageController::class, 'affiliation'])->name('affiliation');
});

Route::get('manifest.webmanifest', function () {
    return response()->file(
        public_path('manifest.webmanifest'),
        ['Content-Type' => 'application/manifest+json; charset=utf-8'],
    );
});

Route::get('sw.js', function () {
    return response()->file(
        public_path('sw.js'),
        ['Content-Type' => 'application/javascript; charset=utf-8'],
    );
});
