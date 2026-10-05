<?php

use App\Http\Controllers\Api\V1\Admin\CategoryController;
use App\Http\Controllers\Api\V1\Admin\InventoryController;
use App\Http\Controllers\Api\V1\Admin\ProductController;
use App\Http\Controllers\Api\V1\Admin\ShippingMethodController;
use App\Http\Controllers\Api\V1\Admin\TaxController;
use App\Http\Controllers\Api\V1\AuthenticationController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\CustomerRegistrationController;
use App\Http\Controllers\Api\V1\GuestOrderController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductImageController;
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
    Route::post('/checkouts', [CheckoutController::class, 'store'])->middleware('throttle:30,1')->name('checkouts.store');
    Route::get('/guest-orders/{order}', [GuestOrderController::class, 'show'])->name('guest-orders.show');
    Route::get('/guest-orders/{order}/status', [GuestOrderController::class, 'status'])->name('guest-orders.status');

    Route::get('/setup/status', [SetupController::class, 'status'])->name('setup.status');
    Route::post('/setup/admin', [SetupController::class, 'create'])
        ->middleware(['throttle:setup', 'setup.available'])
        ->name('setup.admin');

    Route::post('/auth/login', [AuthenticationController::class, 'login'])->middleware('throttle:login')->name('auth.login');
    Route::post('/customers/register', [CustomerRegistrationController::class, 'create'])->middleware('throttle:registration')->name('customers.register');
    Route::get('/products/{product}/image', ProductImageController::class)->name('products.image');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthenticationController::class, 'logout'])->name('auth.logout');
        Route::get('/auth/me', [AuthenticationController::class, 'me'])->name('auth.me');

        Route::prefix('admin')->middleware('role:admin')->name('admin.')->group(function (): void {
            Route::get('/products', [ProductController::class, 'index'])->name('products.index');
            Route::post('/products', [ProductController::class, 'store'])->name('products.store');
            Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
            Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
            Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
            Route::post('/products/{product}/image', [ProductController::class, 'replaceImage'])->name('products.image.replace');
            Route::delete('/products/{product}/image', [ProductController::class, 'removeImage'])->name('products.image.remove');
            Route::get('/products/{product}/inventory', [InventoryController::class, 'show'])->name('inventory.show');
            Route::get('/products/{product}/inventory-adjustments', [InventoryController::class, 'index'])->name('inventory-adjustments.index');
            Route::post('/products/{product}/inventory-adjustments', [InventoryController::class, 'store'])->name('inventory-adjustments.store');

            Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
            Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::get('/categories/{category}', [CategoryController::class, 'show'])->name('categories.show');
            Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
            Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

            Route::get('/taxes', [TaxController::class, 'index'])->name('taxes.index');
            Route::post('/taxes', [TaxController::class, 'store'])->name('taxes.store');
            Route::get('/taxes/{tax}', [TaxController::class, 'show'])->name('taxes.show');
            Route::put('/taxes/{tax}', [TaxController::class, 'update'])->name('taxes.update');
            Route::delete('/taxes/{tax}', [TaxController::class, 'destroy'])->name('taxes.destroy');

            Route::get('/shipping-methods', [ShippingMethodController::class, 'index'])->name('shipping-methods.index');
            Route::post('/shipping-methods', [ShippingMethodController::class, 'store'])->name('shipping-methods.store');
            Route::get('/shipping-methods/{shippingMethod}', [ShippingMethodController::class, 'show'])->name('shipping-methods.show');
            Route::put('/shipping-methods/{shippingMethod}', [ShippingMethodController::class, 'update'])->name('shipping-methods.update');
            Route::delete('/shipping-methods/{shippingMethod}', [ShippingMethodController::class, 'destroy'])->name('shipping-methods.destroy');
        });

        Route::middleware('role:customer')->group(function (): void {
            Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
            Route::put('/cart/items/{product}', [CartController::class, 'setItem'])->name('cart.items.set');
            Route::delete('/cart/items/{product}', [CartController::class, 'deleteItem'])->name('cart.items.delete');
            Route::put('/cart/shipping-method', [CartController::class, 'setShippingMethod'])->name('cart.shipping-method.set');
            Route::post('/cart/merge', [CartController::class, 'merge'])->name('cart.merge');
            Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
            Route::get('/orders/{order}/status', [OrderController::class, 'status'])->name('orders.status');
        });
    });
});
