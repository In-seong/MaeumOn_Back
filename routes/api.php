<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\InsuranceCompanyController;
use App\Http\Controllers\Api\Admin\ClaimFormController;
use App\Http\Controllers\Api\Admin\FormFieldController;
use App\Http\Controllers\Api\Admin\FormPageController;
use App\Http\Controllers\Api\CustomerAuthController;
use App\Http\Controllers\Api\ClaimController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// 인증 API
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

// 고객 인증 API (OTP + PIN)
Route::prefix('customer-auth')->group(function () {
    Route::post('/send-otp', [CustomerAuthController::class, 'sendOtp']);
    Route::post('/verify-otp', [CustomerAuthController::class, 'verifyOtp']);
    Route::post('/register', [CustomerAuthController::class, 'register']);
    Route::post('/set-pin', [CustomerAuthController::class, 'setPin']);
    Route::post('/login-pin', [CustomerAuthController::class, 'loginWithPin']);
    Route::post('/login-pin-new-device', [CustomerAuthController::class, 'loginPinNewDevice']);
    Route::post('/check-device', [CustomerAuthController::class, 'checkDevice']);
});

// 공개 API (인증 불필요)
Route::get('/insurance-companies', [InsuranceCompanyController::class, 'publicIndex']);
Route::get('/claim-forms', [ClaimFormController::class, 'publicIndex']);
Route::get('/claim-forms/{id}', [ClaimFormController::class, 'publicShow']);

// 인증 필요 API
Route::middleware('auth:sanctum')->group(function () {

    // 관리자 API
    Route::prefix('admin')->middleware('role:ADMIN')->group(function () {
        // 보험사 관리
        Route::apiResource('insurance-companies', InsuranceCompanyController::class);

        // 양식 템플릿 관리
        Route::apiResource('claim-forms', ClaimFormController::class);
        Route::post('/claim-forms/{id}/upload-image', [ClaimFormController::class, 'uploadImage']);

        // 페이지 관리
        Route::get('/claim-forms/{templateId}/pages', [FormPageController::class, 'index']);
        Route::post('/claim-forms/{templateId}/pages', [FormPageController::class, 'store']);
        Route::get('/claim-forms/{templateId}/pages/{pageId}', [FormPageController::class, 'show']);
        Route::put('/claim-forms/{templateId}/pages/{pageId}', [FormPageController::class, 'update']);
        Route::delete('/claim-forms/{templateId}/pages/{pageId}', [FormPageController::class, 'destroy']);
        Route::post('/claim-forms/{templateId}/pages/{pageId}/upload-image', [FormPageController::class, 'uploadImage']);
        Route::put('/claim-forms/{templateId}/pages/reorder', [FormPageController::class, 'reorder']);

        // 필드 관리
        Route::get('/claim-forms/{templateId}/fields', [FormFieldController::class, 'index']);
        Route::post('/claim-forms/{templateId}/fields', [FormFieldController::class, 'store']);
        Route::put('/claim-forms/{templateId}/fields/bulk-update', [FormFieldController::class, 'bulkUpdate']);
        Route::get('/pages/{pageId}/fields', [FormFieldController::class, 'indexByPage']);
        Route::post('/pages/{pageId}/fields', [FormFieldController::class, 'storeToPage']);
        Route::put('/fields/{id}', [FormFieldController::class, 'update']);
        Route::delete('/fields/{id}', [FormFieldController::class, 'destroy']);

        // 청구 관리 (관리자용)
        Route::get('/claims', [ClaimController::class, 'adminIndex']);
        Route::put('/claims/{id}/status', [ClaimController::class, 'updateStatus']);
    });

    // 고객 API
    Route::prefix('claims')->middleware('role:CUSTOMER')->group(function () {
        Route::get('/', [ClaimController::class, 'index']);
        Route::post('/', [ClaimController::class, 'store']);
        Route::get('/{id}', [ClaimController::class, 'show']);
        Route::put('/{id}', [ClaimController::class, 'update']);
        Route::get('/{id}/download/pdf', [ClaimController::class, 'downloadPdf']);
        Route::post('/{id}/send-fax', [ClaimController::class, 'sendFax']);
    });
});
