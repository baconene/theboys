<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\HrisController;

Route::middleware('auth')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1')->group(function () {
    // Public routes — static routes BEFORE dynamic {product} to prevent shadowing
    Route::get('/payment-tenders', [\App\Http\Controllers\Api\V1\PaymentTenderController::class, 'index']);

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/search', [ProductController::class, 'search']);
    Route::get('/products/category/{categoryId}', [ProductController::class, 'byCategory']);
    Route::get('/products/{product}', [ProductController::class, 'show']);

    // Protected routes
    Route::middleware('auth')->group(function () {
        // Categories — write routes (read routes are public above)
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

        // Products — write routes (read routes are public above)
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
        Route::post('/products/{product}', [ProductController::class, 'update']); // FormData / file upload
        Route::post('/products/{product}/calculate-cost', [ProductController::class, 'calculateCost']);
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
        Route::patch('/inventory/{ingredient}', [InventoryController::class, 'update']);
        Route::get('/inventory/{ingredient}/transactions', [InventoryController::class, 'transactions']);

        // Reports
        Route::get('/reports/daily-sales', [ReportController::class, 'dailySales']);
        Route::get('/reports/monthly-sales', [ReportController::class, 'monthlySales']);
        Route::get('/reports/product-sales', [ReportController::class, 'productSales']);
        Route::get('/reports/inventory-valuation', [ReportController::class, 'inventoryValuation']);
        Route::get('/reports/inventory-transactions', [ReportController::class, 'inventoryTransactions']);
        Route::get('/reports/profit-loss', [ReportController::class, 'profitLoss']);

        // Payment Tenders (authenticated write + all-list)
        Route::get('/payment-tenders/all', [\App\Http\Controllers\Api\V1\PaymentTenderController::class, 'all']);
        Route::post('/payment-tenders', [\App\Http\Controllers\Api\V1\PaymentTenderController::class, 'store']);
        Route::put('/payment-tenders/{paymentTender}', [\App\Http\Controllers\Api\V1\PaymentTenderController::class, 'update']);
        Route::delete('/payment-tenders/{paymentTender}', [\App\Http\Controllers\Api\V1\PaymentTenderController::class, 'destroy']);

        // Financial Transactions
        Route::get('/financial-transactions', [\App\Http\Controllers\Api\V1\FinancialTransactionController::class, 'index']);
        Route::get('/financial-transactions/summary', [\App\Http\Controllers\Api\V1\FinancialTransactionController::class, 'summary']);
        Route::post('/financial-transactions', [\App\Http\Controllers\Api\V1\FinancialTransactionController::class, 'store']);
        Route::delete('/financial-transactions/{financialTransaction}', [\App\Http\Controllers\Api\V1\FinancialTransactionController::class, 'destroy']);

        // Bills / Payables
        Route::get('/bills/forecast', [\App\Http\Controllers\Api\V1\BillController::class, 'forecast']);
        Route::get('/bills', [\App\Http\Controllers\Api\V1\BillController::class, 'index']);
        Route::post('/bills', [\App\Http\Controllers\Api\V1\BillController::class, 'store']);
        Route::put('/bills/{bill}', [\App\Http\Controllers\Api\V1\BillController::class, 'update']);
        Route::delete('/bills/{bill}', [\App\Http\Controllers\Api\V1\BillController::class, 'destroy']);
        Route::post('/bills/{bill}/pay', [\App\Http\Controllers\Api\V1\BillController::class, 'pay']);

        // HRIS — Employees
        Route::get('/hris/employees', [HrisController::class, 'employees']);
        Route::post('/hris/employees', [HrisController::class, 'storeEmployee']);
        Route::put('/hris/employees/{employee}', [HrisController::class, 'updateEmployee']);
        Route::delete('/hris/employees/{employee}', [HrisController::class, 'destroyEmployee']);

        // HRIS — Payroll
        Route::get('/hris/payroll', [HrisController::class, 'payrollRecords']);
        Route::post('/hris/payroll', [HrisController::class, 'storePayroll']);
        Route::post('/hris/payroll/{payrollRecord}/pay', [HrisController::class, 'markPayrollPaid']);
        Route::delete('/hris/payroll/{payrollRecord}', [HrisController::class, 'destroyPayroll']);
    });
});
