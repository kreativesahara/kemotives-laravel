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
use App\Http\Controllers\Api\AccessoriesController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\FilterController;
use App\Http\Controllers\Api\CompareController;
use App\Http\Controllers\Api\SellersController;
use App\Http\Controllers\Api\UsersController;
use App\Http\Controllers\Api\AgentController;
use App\Http\Middleware\VerifyWebhookSignature;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

Route::get('refresh', [AuthController::class, 'refresh']);
Route::get('logout', [AuthController::class, 'logout']);

Route::post('forgot-password', [PasswordResetController::class, 'requestReset']);
Route::post('reset-password/{token}', [PasswordResetController::class, 'resetPassword']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// ──────────────────────────────────────────────────────────
// Public Routes
// ──────────────────────────────────────────────────────────
// Webhooks
Route::post('/webhook/paystack', [PaymentWebhookController::class, 'handlePaystackWebhook']);
Route::post('/webhook/mpesa', [PaymentWebhookController::class, 'handleMpesaWebhook']);

// Blog
Route::get('/blogs', [BlogController::class, 'getAllBlogs']);
Route::get('/blogs/{slug}', [BlogController::class, 'getBlogBySlug']);
Route::patch('/blogs/view/{slug}', [BlogController::class, 'trackBlogView']);

// Votes
Route::get('/blogs/{blogId}/votes/total', [VoteController::class, 'getTotalVotes']);
Route::get('/blogs/{blogId}/vote', [VoteController::class, 'getBlogVotes']);

// Search & Filter & Compare
Route::get('/search', [SearchController::class, 'searchCarProduct']);
Route::get('/filter', [FilterController::class, 'filterCarProducts']);
Route::get('/filter/accessories', [FilterController::class, 'filterAccessoryProducts']);
Route::get('/compare', [CompareController::class, 'getCompareItems']);

// Agent
Route::post('/agent/chat', [AgentController::class, 'agentChat']);
Route::get('/agent/greeting', [AgentController::class, 'agentGreeting']);

// Accessories (Public read)
Route::get('/accessories', [AccessoriesController::class, 'getAllAccessories']);
Route::get('/accessories/{slug}', [AccessoriesController::class, 'getAccessoryById']);
Route::get('/accessories/seller/{sellerId}', [AccessoriesController::class, 'getSellerAccessories']);
Route::patch('/accessories/view/{slug}', [AccessoriesController::class, 'trackAccessoryView']);

// Sellers (Public read)
Route::get('/sellers', [SellersController::class, 'getAllSellers']);
Route::get('/sellers/{id}', [SellersController::class, 'getSeller']);

// Users (Public read)
Route::get('/users', [UsersController::class, 'getAllUsers']);
Route::get('/users/{id}', [UsersController::class, 'getUser']);

// ──────────────────────────────────────────────────────────
// Protected Routes (Require Authentication)
// ──────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    // Users
    Route::get('/users/me', [UsersController::class, 'getMe']);

    // Subscriptions & Payments
    Route::get('/subscriptions', [SubscriptionController::class, 'getUserSubscription']);
    Route::post('/subscriptions', [SubscriptionController::class, 'createSubscription']);
    Route::get('/subscriptions/history', [SubscriptionController::class, 'getSubscriptionHistory']);
    Route::get('/payments/history', [PaymentController::class, 'getPaymentHistory']);

    // KYC
    Route::post('/kyc/submit', [KycController::class, 'submitKYC']);
    Route::get('/kyc/user/{userId}', [KycController::class, 'getKYCByUserId']);
    Route::patch('/kyc/{kycId}/status', [KycController::class, 'updateKYCStatus']); // Admin Only
    Route::get('/kyc', [KycController::class, 'getAllKYCs']); // Admin Only
    Route::get('/kyc/seller/{sellerId}', [KycController::class, 'getKYC']); // Admin Only

    // Blogs (Authenticated actions)
    Route::post('/blogs', [BlogController::class, 'createBlog']);
    Route::patch('/blogs/{id}', [BlogController::class, 'updateBlog']);
    Route::delete('/blogs/{id}', [BlogController::class, 'deleteBlog']);
    Route::patch('/blogs/{id}/publish', [BlogController::class, 'publishBlog']);

    // Votes
    Route::post('/blogs/{blogId}/vote', [VoteController::class, 'handleVote']);

    // Accessories
    Route::post('/accessories', [AccessoriesController::class, 'createAccessory']);
    Route::patch('/accessories/{id}', [AccessoriesController::class, 'updateAccessory']);
    Route::delete('/accessories/{id}', [AccessoriesController::class, 'deleteAccessory']);
    Route::patch('/accessories/toggle-status', [AccessoriesController::class, 'toggleAccessoriesActiveStatus']);

    // Sellers
    Route::post('/sellers', [SellersController::class, 'createSeller']);
    Route::patch('/sellers/{id}', [SellersController::class, 'updateSeller']);
    Route::delete('/sellers/{id}', [SellersController::class, 'deleteSeller']);

    // Users (Writes)
    Route::post('/users', [UsersController::class, 'createUser']);
    Route::patch('/users', [UsersController::class, 'updateUser']);
    Route::delete('/users', [UsersController::class, 'deleteUser']);
    Route::get('/product/seller/{sellerId}', [VehicleController::class, 'sellerProducts']);
    Route::get('/product/listing-limit/{sellerId}', [VehicleController::class, 'limitStatus']);
    Route::post('/product', [VehicleController::class, 'store']);
    Route::patch('/product', [VehicleController::class, 'update']);
    Route::patch('/product/toggle-status/{id}', [VehicleController::class, 'toggleActiveStatus']);
    Route::delete('/product/{id}', [VehicleController::class, 'destroy']);
});

Route::get('/publicproducts', [VehicleController::class, 'index']);
Route::get('/product/{slug}', [VehicleController::class, 'show']);
Route::post('/product/{slug}/track-view', [VehicleController::class, 'trackView']);
