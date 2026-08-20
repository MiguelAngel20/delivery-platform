<?php

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

Route::prefix('legal')->name('legal.')->group(function () {
    Route::get('terminos-y-condiciones', [LegalPageController::class, 'terms'])->name('terms');
    Route::get('aviso-de-privacidad', [LegalPageController::class, 'privacy'])->name('privacy');
    Route::get('quejas-y-sugerencias', [LegalPageController::class, 'feedback'])->name('feedback');
    Route::get('afiliacion', [LegalPageController::class, 'affiliation'])->name('affiliation');
});
