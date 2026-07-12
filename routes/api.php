<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\KycController;
use App\Http\Controllers\Api\BlogController;
use App\Http\Controllers\Api\VoteController;
use App\Http\Middleware\VerifyWebhookSignature;

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

    // Payments
    Route::post('/payments/initiate', [PaymentController::class, 'initiatePayment']);
    Route::post('/payments/verify', [PaymentController::class, 'verifyPayment']);

    // KYC
    Route::post('/kyc/submit', [KycController::class, 'submitKYC']);
    Route::get('/kyc/user/{userId}', [KycController::class, 'getKYCByUserId']);
    Route::patch('/kyc/{kycId}/status', [KycController::class, 'updateKYCStatus']); // Admin Only (TODO: add admin middleware if needed)
    Route::get('/kyc', [KycController::class, 'getAllKYCs']); // Admin Only
    Route::get('/kyc/seller/{sellerId}', [KycController::class, 'getKYC']); // Admin Only

    // Blogs (Authenticated actions)
    Route::post('/blogs', [BlogController::class, 'createBlog']);
    Route::patch('/blogs/{id}', [BlogController::class, 'updateBlog']);
    Route::delete('/blogs/{id}', [BlogController::class, 'deleteBlog']);
    Route::patch('/blogs/{id}/publish', [BlogController::class, 'publishBlog']);

    // Votes
    Route::post('/votes/{blogId}', [VoteController::class, 'handleVote']);
});

Route::get('/publicproducts', [VehicleController::class, 'index']);
Route::get('/search', [VehicleController::class, 'search']);

// Public Blogs & Votes
Route::get('/blogs', [BlogController::class, 'getPublishedBlogs']);
Route::get('/blogs/{slug}', [BlogController::class, 'getBlogBySlug']);
Route::get('/votes/{blogId}/total', [VoteController::class, 'getTotalVotes']);
Route::get('/votes/{blogId}', [VoteController::class, 'getBlogVotes']);
Route::get('/votes/{blogId}/user', [VoteController::class, 'getUserVote']);

// Webhooks
Route::post('/webhooks/payment', [PaymentWebhookController::class, 'paymentWebhook'])
    ->middleware(VerifyWebhookSignature::class);
Route::get('/product/{slug}', [VehicleController::class, 'show']);
Route::post('/product/{slug}/track-view', [VehicleController::class, 'trackView']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
