<?php

use App\Http\Controllers\Api\V1\AuthenticationController;
use App\Http\Controllers\Api\V1\CustomerRegistrationController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\SetupController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health/live', [HealthController::class, 'live'])->name('health.live');
    Route::get('/health/ready', [HealthController::class, 'ready'])->name('health.ready');

    Route::get('/setup/status', [SetupController::class, 'status'])->name('setup.status');
    Route::post('/setup/admin', [SetupController::class, 'create'])
        ->middleware(['throttle:setup', 'setup.available'])
        ->name('setup.admin');

    Route::post('/auth/login', [AuthenticationController::class, 'login'])->middleware('throttle:login')->name('auth.login');
    Route::post('/customers/register', [CustomerRegistrationController::class, 'create'])->middleware('throttle:registration')->name('customers.register');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthenticationController::class, 'logout'])->name('auth.logout');
        Route::get('/auth/me', [AuthenticationController::class, 'me'])->name('auth.me');
    });
});
