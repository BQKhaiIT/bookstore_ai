<?php

use App\Http\Controllers\PasswordController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
        Route::get('/me', [ProfileController::class, 'show']);
        Route::patch('/me', [ProfileController::class, 'update']);
        Route::patch('/me/password', [PasswordController::class, 'update']);
    });

    Route::post('/checkout-probe', function () {
        return response()->json(['data' => ['message' => 'verified']]);
    })->middleware(['auth:sanctum', 'active', 'verified']);

    Route::get('/admin/probe', function () {
        return response()->json(['data' => ['message' => 'admin']]);
    })->middleware(['auth:sanctum', 'active', 'verified', 'admin']);

    Route::prefix('admin')->group(base_path('routes/admin.php'));
});

Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'BookStore API is running',
    ]);
});
