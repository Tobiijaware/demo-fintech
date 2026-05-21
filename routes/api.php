<?php

use App\Http\Controllers\Api\Admin\BackofficeUserController;
use App\Http\Controllers\Api\Admin\OnboardingApplicationController;
use App\Http\Controllers\Api\Admin\OnboardingDocumentController;
use App\Http\Controllers\Api\Admin\PermissionController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Api\KycController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\Admin\TransactionController as AdminTransactionController;
use App\Http\Controllers\Api\TransactionController;
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
        Route::post('wallet/resolve', [TransactionController::class, 'resolve']);
        Route::post('wallet/transfer', [TransactionController::class, 'transfer']);
        Route::get('transactions', [TransactionController::class, 'index']);
        Route::get('transactions/{reference}', [TransactionController::class, 'show']);
        Route::get('banks', [BankController::class, 'index']);
        Route::post('banks/resolve', [BankController::class, 'resolve']);

        Route::prefix('kyc')->group(function () {
            Route::get('tier-requirements', [KycController::class, 'tierRequirements']);
            Route::get('tier-definitions', [KycController::class, 'tierDefinitions']);
            Route::get('progress', [KycController::class, 'progress']);
            Route::post('bvn/validate', [KycController::class, 'validateBvn']);
            Route::post('nin/validate', [KycController::class, 'validateNin']);
            Route::post('documents', [KycController::class, 'storeDocument']);
            Route::post('submit', [KycController::class, 'submit']);
        });

        Route::prefix('admin')->middleware('backoffice.permission:user_management,read')->group(function () {
            Route::get('permissions', [PermissionController::class, 'index']);

            Route::get('roles', [RoleController::class, 'index']);
            Route::post('roles', [RoleController::class, 'store'])->middleware('backoffice.permission:user_management,write');
            Route::get('roles/{role}', [RoleController::class, 'show']);
            Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('backoffice.permission:user_management,write');
            Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('backoffice.permission:user_management,write');

            Route::get('users', [BackofficeUserController::class, 'index']);
            Route::post('users', [BackofficeUserController::class, 'store'])->middleware('backoffice.permission:user_management,write');
            Route::put('users/{user}', [BackofficeUserController::class, 'update'])->middleware('backoffice.permission:user_management,write');
            Route::delete('users/{user}', [BackofficeUserController::class, 'destroy'])->middleware('backoffice.permission:user_management,write');
        });

        Route::prefix('admin/transactions')->middleware('backoffice.permission:transactions,read')->group(function () {
            Route::get('stats', [AdminTransactionController::class, 'stats']);
            Route::get('/', [AdminTransactionController::class, 'index']);
        });

        Route::prefix('admin/onboarding')->middleware('backoffice.permission:kyc_applications,read')->group(function () {
            Route::get('stats', [OnboardingApplicationController::class, 'stats']);
            Route::get('tier-definitions', [OnboardingApplicationController::class, 'tierDefinitions']);
            Route::get('/', [OnboardingApplicationController::class, 'index']);
            Route::get('{onboardingApplication}', [OnboardingApplicationController::class, 'show']);
            Route::post('/', [OnboardingApplicationController::class, 'store'])->middleware('backoffice.permission:kyc_applications,write');
            Route::post('{onboardingApplication}/submit', [OnboardingApplicationController::class, 'submit'])->middleware('backoffice.permission:kyc_applications,write');
            Route::post('{onboardingApplication}/approve', [OnboardingApplicationController::class, 'approve'])->middleware('backoffice.permission:kyc_applications,write');
            Route::post('{onboardingApplication}/reject', [OnboardingApplicationController::class, 'reject'])->middleware('backoffice.permission:kyc_applications,write');
            Route::post('{onboardingApplication}/query', [OnboardingApplicationController::class, 'query'])->middleware('backoffice.permission:kyc_applications,write');
            Route::post('{onboardingApplication}/hold', [OnboardingApplicationController::class, 'hold'])->middleware('backoffice.permission:kyc_applications,write');

            Route::get('{onboardingApplication}/documents', [OnboardingDocumentController::class, 'index']);
            Route::post('{onboardingApplication}/documents', [OnboardingDocumentController::class, 'store'])->middleware('backoffice.permission:kyc_applications,write');
        });

        Route::prefix('admin/onboarding/documents')->middleware('backoffice.permission:kyc_applications,read')->group(function () {
            Route::get('{onboardingDocument}/file', [OnboardingDocumentController::class, 'show']);
            Route::delete('{onboardingDocument}', [OnboardingDocumentController::class, 'destroy'])->middleware('backoffice.permission:kyc_applications,write');
        });
    });
});
