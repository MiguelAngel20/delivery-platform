<?php

use App\Http\Controllers\Web\Public\HomeController;
use App\Http\Controllers\Web\Public\RestaurantController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', HomeController::class)->name('home');

Route::get('restaurants', [RestaurantController::class, 'index'])->name('restaurants.index');
Route::get('restaurants/{slug}', [RestaurantController::class, 'show'])->name('restaurants.show');

Route::get('categories', fn () => Inertia::render('public/categories/index'))
    ->name('categories.index');

Route::get('promotions', fn () => Inertia::render('public/promotions/index'))
    ->name('promotions.index');

Route::get('search', function (Request $request) {
    return Inertia::render('public/search/index', [
        'q' => $request->string('q')->toString(),
    ]);
})->name('search');

Route::get('cart', fn () => Inertia::render('public/cart/index'))
    ->name('cart');
