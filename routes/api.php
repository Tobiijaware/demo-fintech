<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::prefix('register')->group(function () {
            Route::post('email', [RegistrationController::class, 'sendCode']);
            Route::post('resend-code', [RegistrationController::class, 'resendCode']);
            Route::post('verify-email', [RegistrationController::class, 'verifyEmail']);
            Route::post('complete', [RegistrationController::class, 'complete']);
        });

        Route::post('login', [AuthController::class, 'login']);

        Route::middleware('auth:api')->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('setup-pin', [AuthController::class, 'setupPin']);
            Route::post('refresh', [AuthController::class, 'refresh']);
            Route::post('logout', [AuthController::class, 'logout']);
        });
    });

    Route::middleware('auth:api')->group(function () {
        Route::get('wallet/balance', [WalletController::class, 'balance']);
    });
});
