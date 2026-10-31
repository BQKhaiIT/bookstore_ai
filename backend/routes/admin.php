<?php

use App\Http\Controllers\Admin\AuthorController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\BookController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CloudinaryMediaController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PublisherController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active', 'verified', 'admin'])->group(function (): void {
    Route::get('/cloudinary/images', [CloudinaryMediaController::class, 'index']);

    Route::middleware('permission:dashboard')->get('/dashboard', [DashboardController::class, 'show']);

    Route::middleware('super_admin')->prefix('staff')->group(function (): void {
        Route::get('/', [StaffController::class, 'index']);
        Route::post('/invitations', [StaffController::class, 'invite']);
        Route::delete('/invitations/{invitation}', [StaffController::class, 'destroyInvitation']);
        Route::patch('/users/{user}/permissions', [StaffController::class, 'updatePermissions']);
        Route::patch('/users/{user}/status', [StaffController::class, 'updateStatus']);
    });

    Route::middleware('permission:books')->group(function (): void {
        Route::post('/books/{book:id}/cover', [BookController::class, 'uploadCover']);
        Route::put('/books/{book:id}/cover', [BookController::class, 'assignCover']);
        Route::delete('/books/{book:id}/cover', [BookController::class, 'removeCover']);
        Route::apiResource('books', BookController::class)->parameters(['books' => 'book:id']);
    });

    Route::middleware('permission:categories')->apiResource('categories', CategoryController::class);
    Route::middleware('permission:authors')->apiResource('authors', AuthorController::class);
    Route::middleware('permission:publishers')->apiResource('publishers', PublisherController::class);

    Route::middleware('permission:orders')->group(function (): void {
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
    });

    Route::middleware('permission:payments')->group(function (): void {
        Route::get('/payments', [PaymentController::class, 'index']);
        Route::post('/payments/{payment}/confirm-cod', [PaymentController::class, 'confirmCod']);
    });

    Route::middleware('permission:users')->group(function (): void {
        Route::get('/users', [UserController::class, 'index']);
        Route::patch('/users/{user}/status', [UserController::class, 'updateStatus']);
    });

    Route::middleware('permission:reviews')->group(function (): void {
        Route::get('/reviews', [ReviewController::class, 'index']);
        Route::patch('/reviews/{review}', [ReviewController::class, 'update']);
    });

    Route::middleware('permission:banners')->group(function (): void {
        Route::post('/banners/{banner:id}/image', [BannerController::class, 'uploadImage']);
        Route::apiResource('banners', BannerController::class)->parameters(['banners' => 'banner:id']);
    });
});
