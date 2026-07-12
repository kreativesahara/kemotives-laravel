<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\SubscriptionController;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

Route::get('refresh', [AuthController::class, 'refresh']);
Route::get('logout', [AuthController::class, 'logout']);

Route::post('forgot-password', [PasswordResetController::class, 'requestReset']);
Route::post('reset-password/{token}', [PasswordResetController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/users/me', [AuthController::class, 'me']);
    
    Route::post('/product', [VehicleController::class, 'store']);
    Route::put('/product', [VehicleController::class, 'update']);
    Route::delete('/product/{id}', [VehicleController::class, 'destroy']);
    Route::patch('/product/{id}/status', [VehicleController::class, 'toggleActiveStatus']);
    Route::get('/sellers/{id}/products', [VehicleController::class, 'sellerProducts']);
    Route::get('/sellers/{id}/limit-status', [VehicleController::class, 'limitStatus']);

    // Subscriptions
    Route::post('/subscriptions', [SubscriptionController::class, 'subscribe']);
    Route::patch('/subscriptions/update', [SubscriptionController::class, 'updateSubscription']);
    Route::get('/subscriptions/{userId}', [SubscriptionController::class, 'getSubscription']);
    Route::delete('/subscriptions/cancel/{id}', [SubscriptionController::class, 'cancelSubscription']);
    Route::get('/subscriptions/seller-status/{userId}', [SubscriptionController::class, 'checkSellerStatusAndSubscription']);
});

Route::get('/publicproducts', [VehicleController::class, 'index']);
Route::get('/product/{slug}', [VehicleController::class, 'show']);
Route::post('/product/{slug}/track-view', [VehicleController::class, 'trackView']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
