<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - V1
|--------------------------------------------------------------------------
|
| Versioned RESTful API routes for the Order & Payment Management System.
|
*/

Route::prefix('v1')->group(function () {

    // Authentication Endpoints (Public)
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->name('auth.register');
        Route::post('login', [AuthController::class, 'login'])->name('auth.login');
    });

    // Protected API Endpoints (Requires JWT Authentication)
    Route::middleware('auth:api')->group(function () {

        // User Auth Operations
        Route::prefix('auth')->group(function () {
            Route::get('me', [AuthController::class, 'me'])->name('auth.me');
            Route::post('refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
            Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
        });

        // Orders Management
        Route::apiResource('orders', OrderController::class);
        Route::get('orders/{order}/payments', [PaymentController::class, 'getOrderPayments'])->name('orders.payments');

        // Payments Management
        Route::get('payments/gateways', function (PaymentGatewayManager $manager) {
            return response()->json([
                'success' => true,
                'data' => [
                    'default' => $manager->getDefaultGateway(),
                    'available_gateways' => $manager->getAvailableGateways(),
                ],
            ]);
        })->name('payments.gateways');

        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    });
});
