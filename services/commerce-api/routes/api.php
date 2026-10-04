<?php

use App\Http\Controllers\Api\V1\AuthenticationController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\CustomerRegistrationController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\SetupController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health/live', [HealthController::class, 'live'])->name('health.live');
    Route::get('/health/ready', [HealthController::class, 'ready'])->name('health.ready');

    Route::get('/products', [CatalogController::class, 'index'])->name('products.index');
    Route::get('/products/{product}', [CatalogController::class, 'show'])->name('products.show');
    Route::get('/categories', [CatalogController::class, 'categories'])->name('categories.index');
    Route::get('/shipping-methods', [CatalogController::class, 'shippingMethods'])->name('shipping-methods.index');
    Route::post('/cart/quote', [CartController::class, 'quote'])->middleware('throttle:60,1')->name('cart.quote');

    Route::get('/setup/status', [SetupController::class, 'status'])->name('setup.status');
    Route::post('/setup/admin', [SetupController::class, 'create'])
        ->middleware(['throttle:setup', 'setup.available'])
        ->name('setup.admin');

    Route::post('/auth/login', [AuthenticationController::class, 'login'])->middleware('throttle:login')->name('auth.login');
    Route::post('/customers/register', [CustomerRegistrationController::class, 'create'])->middleware('throttle:registration')->name('customers.register');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthenticationController::class, 'logout'])->name('auth.logout');
        Route::get('/auth/me', [AuthenticationController::class, 'me'])->name('auth.me');

        Route::middleware('role:customer')->group(function (): void {
            Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
            Route::put('/cart/items/{product}', [CartController::class, 'setItem'])->name('cart.items.set');
            Route::delete('/cart/items/{product}', [CartController::class, 'deleteItem'])->name('cart.items.delete');
            Route::put('/cart/shipping-method', [CartController::class, 'setShippingMethod'])->name('cart.shipping-method.set');
            Route::post('/cart/merge', [CartController::class, 'merge'])->name('cart.merge');
        });
    });
});
