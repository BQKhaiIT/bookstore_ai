<?php

use App\Http\Controllers\Auth\AdminActivationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [RegisteredUserController::class, 'store'])
    ->middleware('throttle:10,1');

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('throttle:10,1');

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware(['auth:web', 'active']);

Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
    ->middleware('throttle:6,1');

Route::post('/reset-password', [NewPasswordController::class, 'store'])
    ->middleware('throttle:6,1');

Route::post('/admin/activate', [AdminActivationController::class, 'store'])
    ->middleware('throttle:6,1');

Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
    ->middleware('throttle:6,1')
    ->name('verification.verify');

Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
    ->middleware(['auth:web', 'active', 'throttle:6,1']);
