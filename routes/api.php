<?php

use App\Http\Controllers\Api\AttributeController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// ==============
// Public
// ==============
Route::middleware('throttle:login')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('throttle:register')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('throttle:api')->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
});

Route::get('/attributes', [AttributeController::class, 'index']);
Route::get('/details', [ProductController::class, 'showDetails']);
// Route::delete('/attributes/{attribute}', [AttributeController::class, 'deleteAttribute']);

// ======================
// Customer routes
// ======================
Route::middleware(['auth:sanctum', 'role:customer', 'throttle:api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Cart
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'add']);
    Route::put('/cart/{id}', [CartController::class, 'update']);
    Route::patch('/cart/{id}/decrease', [CartController::class, 'decreaseQuantity']);
    Route::delete('/cart/{id}', [CartController::class, 'remove']);
    Route::delete('/cart', [CartController::class, 'clear']);

    // Orders
    Route::middleware('throttle:orders')->group(function () {
        Route::post('/orders', [OrderController::class, 'store']);
    });
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::get('/orders/{id}/invoice', [OrderController::class, 'downloadInvoice']);
    Route::get('/orders/{id}/similar', [OrderController::class, 'similargroup']);
});

// ==================
// Admin routes
// ==================

Route::middleware(['auth:sanctum', 'role:admin', 'throttle:api'])->group(function () {
    // Products
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    Route::patch('/variants/{id}', [ProductController::class, 'updateVariant']);
    Route::delete('/products/{productId}/images/{imageId}', [ProductController::class, 'destroyImage']);

    // Orders
    Route::get('/admin/orders', [OrderController::class, 'adminIndex']);
    Route::put('/admin/orders/{id}', [OrderController::class, 'updateStatus']);
    Route::patch('/admin/orders/{id}', [OrderController::class, 'updateStatus']);
    Route::get('/admin/similarorders', [OrderController::class, 'specificOrder']);

    // Attributes
    Route::post('/attributes', [AttributeController::class, 'store']);
    Route::post('/attributes/{attribute}/values', [AttributeController::class, 'addValue']);
    Route::delete('/attribute-values/{value}', [AttributeController::class, 'deleteValue']);
});
