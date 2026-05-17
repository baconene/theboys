<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\ReportController;

Route::middleware('auth')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1')->group(function () {
    // Public routes — static routes BEFORE dynamic {product} to prevent shadowing
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/search', [ProductController::class, 'search']);
    Route::get('/products/category/{categoryId}', [ProductController::class, 'byCategory']);
    Route::get('/products/{product}', [ProductController::class, 'show']);

    // Protected routes
    Route::middleware('auth')->group(function () {
        // Products — write routes (read routes are public above)
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);

        // Orders — static sub-routes BEFORE apiResource to avoid {order} shadowing
        Route::get('/orders/active', [OrderController::class, 'activeOrders']);
        Route::get('/orders/queue', [OrderController::class, 'queueOrders']);
        Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
        Route::apiResource('orders', OrderController::class);

        // Payments
        Route::post('/payments', [PaymentController::class, 'store']);
        Route::get('/orders/{orderId}/payments', [PaymentController::class, 'orderPayments']);
        Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund']);

        // Inventory — static sub-routes BEFORE {ingredient}
        Route::post('/inventory', [InventoryController::class, 'store']);
        Route::get('/inventory', [InventoryController::class, 'index']);
        Route::get('/inventory/low-stock', [InventoryController::class, 'lowStock']);
        Route::post('/inventory/adjust', [InventoryController::class, 'adjust']);
        Route::get('/inventory/{ingredient}/transactions', [InventoryController::class, 'transactions']);

        // Reports
        Route::get('/reports/daily-sales', [ReportController::class, 'dailySales']);
        Route::get('/reports/monthly-sales', [ReportController::class, 'monthlySales']);
        Route::get('/reports/product-sales', [ReportController::class, 'productSales']);
        Route::get('/reports/inventory-valuation', [ReportController::class, 'inventoryValuation']);
    });
});
