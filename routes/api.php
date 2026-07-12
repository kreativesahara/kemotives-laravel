<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PasswordResetController;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

Route::get('refresh', [AuthController::class, 'refresh']);
Route::get('logout', [AuthController::class, 'logout']);

Route::post('forgot-password', [PasswordResetController::class, 'requestReset']);
Route::post('reset-password/{token}', [PasswordResetController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
