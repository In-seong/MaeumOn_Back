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
use App\Http\Controllers\Api\Admin\AdminNoticeController;
use App\Http\Controllers\Api\Admin\AdminCustomerController;
use App\Http\Controllers\Api\Admin\AdminAgentController;
use App\Http\Controllers\Api\Admin\AdminMemoController;
use App\Http\Controllers\Api\Admin\AdminAssignmentController;
use App\Http\Controllers\Api\Admin\AdminAdditionalContractController;
use App\Http\Controllers\Api\Admin\AdminPerformanceController;
use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Agent\AgentBatchClaimController;
use App\Http\Controllers\Api\Agent\AgentScheduleController;
use App\Http\Controllers\Api\Agent\AgentFcmTokenController;
use App\Http\Controllers\Api\Admin\AdminNotificationController;
use App\Http\Controllers\Api\StandardFieldController;
use App\Http\Controllers\Api\Admin\AdminConsentTemplateController;
use App\Http\Controllers\Api\Admin\AdminConsultationController;
use App\Http\Controllers\Api\Admin\AdminBatchClaimController;

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
        Route::put('/change-password', [AuthController::class, 'changePassword']);
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
Route::get('/standard-fields', [StandardFieldController::class, 'index']);
Route::get('/consent-templates', [AdminConsentTemplateController::class, 'index']);

// 인증 필요 API
Route::middleware('auth:sanctum')->group(function () {

    // 관리자 API
    Route::prefix('admin')->middleware('role:ADMIN')->group(function () {
        // 대시보드
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);

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
        Route::get('/claims/{id}', [ClaimController::class, 'adminShow']);
        Route::put('/claims/{id}/status', [ClaimController::class, 'updateStatus']);

        // 공지사항 관리 (SFR-044)
        Route::apiResource('notices', AdminNoticeController::class);

        // 고객 관리 (SFR-032~038)
        Route::apiResource('customers', AdminCustomerController::class)->names('admin.customers');
        Route::get('/customers/{customerId}/memos', [AdminMemoController::class, 'index']);
        Route::post('/customers/{customerId}/memos', [AdminMemoController::class, 'store']);
        Route::put('/memos/{memoId}', [AdminMemoController::class, 'update']);
        Route::delete('/memos/{memoId}', [AdminMemoController::class, 'destroy']);

        // 설계사 관리 (SFR-042)
        Route::apiResource('agents', AdminAgentController::class);

        // DB 배분 (SFR-039)
        Route::get('/assignments', [AdminAssignmentController::class, 'index']);
        Route::post('/assignments', [AdminAssignmentController::class, 'store']);
        Route::post('/assignments/bulk', [AdminAssignmentController::class, 'bulkStore']);
        Route::delete('/assignments/{id}', [AdminAssignmentController::class, 'destroy']);

        // 추가계약 발굴 (SFR-040, 041)
        Route::get('/additional-contracts', [AdminAdditionalContractController::class, 'index']);

        // 실적 현황 (SFR-043)
        Route::get('/performance/summary', [AdminPerformanceController::class, 'summary']);
        Route::get('/performance/agents', [AdminPerformanceController::class, 'agents']);
        Route::get('/performance/agents/{id}', [AdminPerformanceController::class, 'agentDetail']);

        // 알림 발송 (관리자 → 설계사)
        Route::get('/notifications', [AdminNotificationController::class, 'index']);
        Route::post('/notifications', [AdminNotificationController::class, 'store']);

        // 동의서 관리
        Route::get('/consent-templates', [AdminConsentTemplateController::class, 'index']);
        Route::put('/consent-templates/{id}', [AdminConsentTemplateController::class, 'update']);

        // 상담 관리
        Route::get('/consultations', [AdminConsultationController::class, 'index']);

        // 배치 청구 관리
        Route::get('/batch-claims', [AdminBatchClaimController::class, 'index']);
    });

    // 설계사 API
    Route::prefix('agent')->middleware('role:AGENT')->group(function () {
        // 대시보드
        Route::get('/dashboard', [AgentDashboardController::class, 'index']);
        Route::get('/dashboard/today-tasks', [AgentDashboardController::class, 'todayTasks']);
        Route::get('/profile', [AgentDashboardController::class, 'profile']);
        Route::put('/profile', [AgentDashboardController::class, 'updateProfile']);

        // 고객 관리 (SFR-019~022)
        Route::apiResource('customers', AgentCustomerController::class)->names('agent.customers');
        Route::get('/customers/{id}/contracts', [AgentCustomerController::class, 'contracts']);
        Route::post('/customers/{id}/contracts', [AgentCustomerController::class, 'storeContract']);
        Route::put('/customers/{id}/contracts/{contractId}', [AgentCustomerController::class, 'updateContract']);
        Route::delete('/customers/{id}/contracts/{contractId}', [AgentCustomerController::class, 'destroyContract']);

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
        // 임시저장 (Draft) — {id} 라우트보다 먼저 등록
        Route::post('/claims/draft', [AgentClaimController::class, 'saveDraft']);
        Route::put('/claims/{id}/draft', [AgentClaimController::class, 'updateDraft']);
        Route::post('/claims/{id}/submit', [AgentClaimController::class, 'submitDraft']);
        Route::delete('/claims/{id}/draft', [AgentClaimController::class, 'deleteDraft']);
        Route::get('/claims/{id}', [AgentClaimController::class, 'show']);
        Route::put('/claims/{id}', [AgentClaimController::class, 'update']);
        Route::post('/claims/{id}/send-fax', [AgentClaimController::class, 'sendFax']);
        Route::get('/claims/{id}/fax-status', [AgentClaimController::class, 'refreshFaxStatus']);
        Route::post('/claims/{id}/documents', [AgentClaimController::class, 'uploadDocument']);
        Route::delete('/claims/{id}/documents/{docId}', [AgentClaimController::class, 'deleteDocument']);
        Route::get('/claims/{id}/download/pdf', [AgentClaimController::class, 'downloadPdf']);
        Route::put('/claims/{id}/beneficiary', [AgentClaimController::class, 'updateBeneficiary']);

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

        // 다중 청구 (배치)
        Route::get('/batch-claims', [AgentBatchClaimController::class, 'index']);
        Route::post('/batch-claims', [AgentBatchClaimController::class, 'store']);
        Route::post('/batch-claims/draft', [AgentBatchClaimController::class, 'saveDraft']);
        Route::put('/batch-claims/{id}/draft', [AgentBatchClaimController::class, 'updateDraft']);
        Route::post('/batch-claims/{id}/submit', [AgentBatchClaimController::class, 'submitDraft']);
        Route::delete('/batch-claims/{id}/draft', [AgentBatchClaimController::class, 'deleteDraft']);
        Route::get('/batch-claims/{id}', [AgentBatchClaimController::class, 'show']);
        Route::post('/batch-claims/{id}/send-fax', [AgentBatchClaimController::class, 'sendFax']);

        // 일정/캘린더 (SFR-캘린더)
        Route::get('/schedules', [AgentScheduleController::class, 'index']);
        Route::get('/schedules/month/{year}/{month}', [AgentScheduleController::class, 'month']);
        Route::get('/schedules/upcoming', [AgentScheduleController::class, 'upcoming']);
        Route::post('/schedules', [AgentScheduleController::class, 'store']);
        Route::get('/schedules/{id}', [AgentScheduleController::class, 'show']);
        Route::put('/schedules/{id}', [AgentScheduleController::class, 'update']);
        Route::delete('/schedules/{id}', [AgentScheduleController::class, 'destroy']);
        Route::put('/schedules/{id}/toggle-complete', [AgentScheduleController::class, 'toggleComplete']);

        // FCM 토큰 등록
        Route::post('/fcm-token', [AgentFcmTokenController::class, 'store']);
        Route::delete('/fcm-token', [AgentFcmTokenController::class, 'destroy']);

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
