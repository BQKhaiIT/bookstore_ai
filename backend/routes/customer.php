<?php

use App\Http\Controllers\Customer\AddressController;
use App\Http\Controllers\Customer\BannerController;
use App\Http\Controllers\Customer\CartController;
use App\Http\Controllers\Customer\CatalogBookController;
use App\Http\Controllers\Customer\CatalogMetaController;
use App\Http\Controllers\Customer\CheckoutController;
use App\Http\Controllers\Customer\OrderController;
use App\Http\Controllers\Customer\OrderPaymentController;
use App\Http\Controllers\Customer\ReviewController;
use App\Http\Controllers\Customer\WishlistController;
use Illuminate\Support\Facades\Route;

Route::get('/banners', [BannerController::class, 'index']);

Route::get('/books', [CatalogBookController::class, 'index']);
Route::get('/books/{book:slug}/related', [CatalogBookController::class, 'related']);
Route::get('/books/{book:slug}', [CatalogBookController::class, 'show']);
Route::get('/books/{book:slug}/reviews', [ReviewController::class, 'indexForBook']);

Route::get('/categories', [CatalogMetaController::class, 'categories']);
Route::get('/authors', [CatalogMetaController::class, 'authors']);
Route::get('/publishers', [CatalogMetaController::class, 'publishers']);

Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
    Route::get('/cart', [CartController::class, 'show']);
    Route::post('/cart/items', [CartController::class, 'storeItem']);
    Route::patch('/cart/items/{cartItem}', [CartController::class, 'updateItem']);
    Route::delete('/cart/items/{cartItem}', [CartController::class, 'destroyItem']);

    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist', [WishlistController::class, 'store']);
    Route::delete('/wishlist/{book:id}', [WishlistController::class, 'destroy']);

    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses', [AddressController::class, 'store']);
    Route::patch('/addresses/{address:id}', [AddressController::class, 'update']);
    Route::delete('/addresses/{address:id}', [AddressController::class, 'destroy']);

    Route::get('/books/{book:slug}/reviews/mine', [ReviewController::class, 'mineForBook']);
    Route::post('/books/{book:slug}/reviews', [ReviewController::class, 'store']);
    Route::patch('/reviews/{review}', [ReviewController::class, 'update']);
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::get('/orders/{order}/payment', [OrderPaymentController::class, 'show']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
});

Route::middleware(['auth:sanctum', 'active', 'verified'])->group(function (): void {
    Route::get('/checkout/preview', [CheckoutController::class, 'preview']);
    Route::post('/checkout', [CheckoutController::class, 'store']);
    Route::post('/orders/{order}/payment-sessions', [OrderPaymentController::class, 'storeSession']);
});
