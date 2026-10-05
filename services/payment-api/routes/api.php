<?php

use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\PaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/health/live', [HealthController::class, 'live'])->name('health.live');
Route::get('/health/ready', [HealthController::class, 'ready'])->name('health.ready');

Route::prefix('api/v1')->group(function (): void {
    Route::post('/payments', [PaymentController::class, 'store'])
        ->middleware('throttle:payments')
        ->name('payments.store');
});
