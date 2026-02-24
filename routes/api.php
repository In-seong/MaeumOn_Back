<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\InsuranceCompanyController;
use App\Http\Controllers\Api\Admin\ClaimFormController;
use App\Http\Controllers\Api\Admin\FormFieldController;
use App\Http\Controllers\Api\Admin\FormPageController;
use App\Http\Controllers\Api\CustomerAuthController;
use App\Http\Controllers\Api\ClaimController;
use App\Http\Controllers\Api\Agent\AgentDashboardController;
use App\Http\Controllers\Api\Agent\AgentCustomerController;
use App\Http\Controllers\Api\Agent\AgentMemoController;
use App\Http\Controllers\Api\Agent\AgentConsultationController;
use App\Http\Controllers\Api\Agent\AgentClaimController;
use App\Http\Controllers\Api\Agent\AgentMessageController;
use App\Http\Controllers\Api\Agent\AgentNotificationController;
use App\Http\Controllers\Api\Agent\AgentObligationController;
use App\Http\Controllers\Api\Agent\AgentSatisfactionController;
use App\Http\Controllers\Api\Agent\AgentDbDistributionController;

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

    // 설계사 API
    Route::prefix('agent')->middleware('role:AGENT')->group(function () {
        // 대시보드
        Route::get('/dashboard', [AgentDashboardController::class, 'index']);
        Route::get('/profile', [AgentDashboardController::class, 'profile']);
        Route::put('/profile', [AgentDashboardController::class, 'updateProfile']);

        // 고객 관리 (SFR-019~022)
        Route::apiResource('customers', AgentCustomerController::class);
        Route::get('/customers/{id}/contracts', [AgentCustomerController::class, 'contracts']);

        // 메모 (SFR-025)
        Route::get('/customers/{customerId}/memos', [AgentMemoController::class, 'index']);
        Route::post('/customers/{customerId}/memos', [AgentMemoController::class, 'store']);
        Route::put('/memos/{id}', [AgentMemoController::class, 'update']);
        Route::delete('/memos/{id}', [AgentMemoController::class, 'destroy']);

        // 상담 (SFR-008 관련)
        Route::get('/consultations', [AgentConsultationController::class, 'index']);
        Route::get('/consultations/{id}', [AgentConsultationController::class, 'show']);
        Route::put('/consultations/{id}/answer', [AgentConsultationController::class, 'answer']);

        // 보험 청구 조회 + 대리 청구
        Route::get('/claims', [AgentClaimController::class, 'index']);
        Route::post('/claims', [AgentClaimController::class, 'store']);
        Route::get('/claims/{id}', [AgentClaimController::class, 'show']);
        Route::put('/claims/{id}', [AgentClaimController::class, 'update']);
        Route::post('/claims/{id}/send-fax', [AgentClaimController::class, 'sendFax']);
        Route::post('/claims/{id}/documents', [AgentClaimController::class, 'uploadDocument']);
        Route::delete('/claims/{id}/documents/{docId}', [AgentClaimController::class, 'deleteDocument']);
        Route::get('/claims/{id}/download/pdf', [AgentClaimController::class, 'downloadPdf']);

        // 발송 (SFR-026~028)
        Route::get('/messages', [AgentMessageController::class, 'index']);
        Route::post('/messages', [AgentMessageController::class, 'store']);
        Route::get('/messages/{id}', [AgentMessageController::class, 'show']);

        // 알림 (SFR-003)
        Route::get('/notifications', [AgentNotificationController::class, 'index']);
        Route::put('/notifications/read-all', [AgentNotificationController::class, 'markAllAsRead']);
        Route::put('/notifications/{id}/read', [AgentNotificationController::class, 'markAsRead']);

        // 알릴의무 (SFR-029)
        Route::get('/obligations', [AgentObligationController::class, 'index']);
        Route::get('/obligations/{id}', [AgentObligationController::class, 'show']);

        // 만족도조사 (SFR-030~031)
        Route::get('/satisfaction-surveys', [AgentSatisfactionController::class, 'index']);
        Route::post('/satisfaction-surveys', [AgentSatisfactionController::class, 'store']);
        Route::get('/satisfaction-surveys/{id}', [AgentSatisfactionController::class, 'show']);

        // DB배분 수신
        Route::get('/assignments', [AgentDbDistributionController::class, 'index']);
        Route::get('/assignments/{id}', [AgentDbDistributionController::class, 'show']);
        Route::put('/assignments/{id}/process', [AgentDbDistributionController::class, 'process']);
    });

    // 고객 API
    Route::prefix('claims')->middleware('role:CUSTOMER')->group(function () {
        Route::get('/', [ClaimController::class, 'index']);
        Route::post('/', [ClaimController::class, 'store']);
        Route::get('/{id}', [ClaimController::class, 'show']);
        Route::put('/{id}', [ClaimController::class, 'update']);
        Route::get('/{id}/download/pdf', [ClaimController::class, 'downloadPdf']);
        Route::post('/{id}/send-fax', [ClaimController::class, 'sendFax']);
        Route::post('/{id}/documents', [ClaimController::class, 'uploadDocument']);
        Route::delete('/{id}/documents/{docId}', [ClaimController::class, 'deleteDocument']);
    });
});
