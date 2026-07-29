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
use App\Http\Controllers\Api\Agent\AgentCodefController;
use App\Http\Controllers\Api\Agent\AgentDbDistributionController;
use App\Http\Controllers\Api\Admin\AdminNoticeController;
use App\Http\Controllers\Api\Admin\AdminCustomerController;
use App\Http\Controllers\Api\Admin\AdminAgentController;
use App\Http\Controllers\Api\Admin\AdminMemoController;
use App\Http\Controllers\Api\Admin\AdminAssignmentController;
use App\Http\Controllers\Api\Admin\AdminAdditionalContractController;
use App\Http\Controllers\Api\Admin\AdminCodefBillingController;
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
use App\Http\Controllers\Api\Credit4uController;
use App\Http\Controllers\Api\CustomerConsultationController;
use App\Http\Controllers\Api\FcmTokenController;
use App\Http\Controllers\Api\InsuranceController;
use App\Http\Controllers\Api\HealthConsentController;
use App\Http\Controllers\Api\HealthCheckupController;
use App\Http\Controllers\Api\HiraMedicalInfoController;
use App\Http\Controllers\Api\HealthExaminationController;
use App\Http\Controllers\Api\HealthAgeController;
use App\Http\Controllers\Api\HealthPredictionController;
use App\Http\Controllers\Api\HealthPredictionBatchController;
use App\Http\Controllers\Api\HealthSummaryController;
use App\Http\Controllers\Api\ClaimRequestController;
use App\Http\Controllers\Api\PublicHospitalController;
use App\Http\Controllers\Api\PublicHealthCenterController;
use App\Http\Controllers\Api\HospitalReservationController;
use App\Http\Controllers\Api\HospitalPortalController;
use App\Http\Controllers\Api\Admin\AdminHospitalController;
use App\Http\Controllers\Api\Admin\AdminHealthCenterController;
use App\Http\Controllers\Api\Admin\AdminClaimRequestController;
use App\Http\Controllers\Api\Admin\AdminSettingController;
use App\Http\Controllers\Api\Admin\AdminReservationController;
use App\Http\Controllers\Api\Admin\AdminBannerController;
use App\Http\Controllers\Api\CorporateInquiryController;
use App\Http\Controllers\Api\Admin\AdminCorporateInquiryController;
use App\Http\Controllers\Api\FaxCallbackController;

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

// 비즈모아샷 팩스 결과 콜백 (인증 불필요 — 비즈모아샷 서버에서 호출)
Route::match(['get', 'post'], '/fax/callback', [FaxCallbackController::class, 'handleBizmoaCallback']);

// 공개 API (인증 불필요)
Route::get('/insurance-companies', [InsuranceCompanyController::class, 'publicIndex']);
Route::get('/claim-forms', [ClaimFormController::class, 'publicIndex']);
Route::get('/claim-forms/{id}', [ClaimFormController::class, 'publicShow']);
Route::get('/standard-fields', [StandardFieldController::class, 'index']);
Route::get('/consent-templates', [AdminConsentTemplateController::class, 'index']);

// ========== 공개 API (사용자 앱 리뉴얼 - 인증 불필요) ==========
Route::prefix('public')->group(function () {
    // 간편 청구 신청
    Route::post('/claim-requests', [ClaimRequestController::class, 'store']);

    // 병원 목록/상세/슬롯
    Route::get('/hospitals', [PublicHospitalController::class, 'index']);
    Route::get('/hospitals/{id}', [PublicHospitalController::class, 'show']);
    Route::get('/hospitals/{id}/slots', [PublicHospitalController::class, 'availableSlots']);

    // 건강검진 센터 목록/상세/슬롯
    Route::get('/health-centers', [PublicHealthCenterController::class, 'index']);
    Route::get('/health-centers/{id}', [PublicHealthCenterController::class, 'show']);
    Route::get('/health-centers/{id}/slots', [PublicHealthCenterController::class, 'availableSlots']);

    // 배너 (활성만)
    Route::get('/banners', function () {
        return response()->json([
            'data' => \App\Models\Banner::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    });

    // 예약 신청 / 내 예약 조회 / 취소
    Route::post('/reservations', [HospitalReservationController::class, 'store']);
    Route::get('/my-reservations', [HospitalReservationController::class, 'myReservations']);
    Route::put('/reservations/{id}/cancel', [HospitalReservationController::class, 'cancel']);
});

// ========== 병원 포털 API ==========
Route::prefix('hospital-portal')->group(function () {
    Route::post('/login', [HospitalPortalController::class, 'login']);
    Route::get('/reservations', [HospitalPortalController::class, 'reservations']);
    Route::put('/reservations/{id}/status', [HospitalPortalController::class, 'updateStatus']);
    Route::get('/schedule', [HospitalPortalController::class, 'getSchedule']);
    Route::put('/schedule', [HospitalPortalController::class, 'updateSchedule']);
    Route::post('/image', [HospitalPortalController::class, 'uploadImage']);
});

// 외부 API — 기업용 보험 문의 (API 키 인증)
Route::prefix('corporate-inquiries')->middleware('api-key')->group(function () {
    Route::post('/', [CorporateInquiryController::class, 'store']);
    Route::get('/', [CorporateInquiryController::class, 'index']);
    Route::get('/{id}', [CorporateInquiryController::class, 'show']);
});

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
        Route::get('/customers/{customerId}/resident-number', [AdminCustomerController::class, 'unmaskResidentNumber']);
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
        Route::get('/assignments/claim', [AdminAssignmentController::class, 'claimAssignments']);

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
        Route::get('/consultations/{id}', [AdminConsultationController::class, 'show']);
        Route::put('/consultations/{id}/answer', [AdminConsultationController::class, 'answer']);
        Route::put('/consultations/{id}/assign', [AdminConsultationController::class, 'assign']);

        // 배치 청구 관리
        Route::get('/batch-claims', [AdminBatchClaimController::class, 'index']);

        // FCM 토큰 등록/삭제 (관리자)
        Route::post('/fcm-token', [FcmTokenController::class, 'store']);
        Route::delete('/fcm-token', [FcmTokenController::class, 'destroy']);

        // 병원 관리
        Route::apiResource('hospitals', AdminHospitalController::class);
        Route::delete('hospitals/{id}/force', [AdminHospitalController::class, 'forceDelete']);
        Route::put('hospitals/{id}/activate', [AdminHospitalController::class, 'activate']);
        Route::post('hospitals/{id}/image', [AdminHospitalController::class, 'uploadImage']);
        Route::post('hospitals/{id}/thumbnail', [AdminHospitalController::class, 'uploadThumbnail']);
        Route::delete('hospitals/{id}/thumbnail', [AdminHospitalController::class, 'deleteThumbnail']);
        Route::post('hospitals/{id}/images', [AdminHospitalController::class, 'addImage']);
        Route::delete('hospitals/{id}/images/{imageId}', [AdminHospitalController::class, 'deleteImage']);

        // 건강검진 센터 관리
        Route::apiResource('health-centers', AdminHealthCenterController::class);
        Route::delete('health-centers/{id}/force', [AdminHealthCenterController::class, 'forceDelete']);
        Route::post('health-centers/{id}/thumbnail', [AdminHealthCenterController::class, 'uploadThumbnail']);
        Route::delete('health-centers/{id}/thumbnail', [AdminHealthCenterController::class, 'deleteThumbnail']);
        Route::post('health-centers/{id}/images', [AdminHealthCenterController::class, 'addImage']);
        Route::delete('health-centers/{id}/images/{imageId}', [AdminHealthCenterController::class, 'deleteImage']);

        // 예약 관리
        Route::get('/reservations', [AdminReservationController::class, 'index']);
        Route::put('/reservations/{id}/status', [AdminReservationController::class, 'updateStatus']);

        // 배너 관리
        Route::get('/banners', [AdminBannerController::class, 'index']);
        Route::post('/banners', [AdminBannerController::class, 'store']);
        Route::post('/banners/{id}', [AdminBannerController::class, 'update']);
        Route::delete('/banners/{id}', [AdminBannerController::class, 'destroy']);

        // 청구 신청 관리
        Route::get('/claim-requests', [AdminClaimRequestController::class, 'index']);
        Route::get('/claim-requests/{id}', [AdminClaimRequestController::class, 'show']);
        Route::put('/claim-requests/{id}/assign', [AdminClaimRequestController::class, 'assign']);
        Route::post('/claim-requests/bulk-assign', [AdminClaimRequestController::class, 'bulkAssign']);
        Route::put('/claim-requests/{id}/status', [AdminClaimRequestController::class, 'updateStatus']);

        // CODEF API 사용량/정산
        Route::get('/codef-billing/summary', [AdminCodefBillingController::class, 'monthlySummary']);
        Route::get('/codef-billing/logs', [AdminCodefBillingController::class, 'logs']);
        Route::post('/codef-billing/mark-billed', [AdminCodefBillingController::class, 'markBilled']);

        // 기업용 보험 문의 관리
        Route::get('/corporate-inquiries', [AdminCorporateInquiryController::class, 'index']);
        Route::get('/corporate-inquiries/unassigned', [AdminCorporateInquiryController::class, 'unassigned']);
        Route::get('/corporate-inquiries/{id}', [AdminCorporateInquiryController::class, 'show']);
        Route::put('/corporate-inquiries/{id}', [AdminCorporateInquiryController::class, 'update']);
        Route::post('/corporate-inquiries/assign', [AdminCorporateInquiryController::class, 'assign']);

        // 사이트 설정
        Route::get('/settings', [AdminSettingController::class, 'index']);
        Route::put('/settings', [AdminSettingController::class, 'update']);
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
        Route::get('/customers/{id}/resident-number', [AgentCustomerController::class, 'unmaskResidentNumber']);
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

        // 청구 배정 조회
        Route::get('/claim-assignments', [AgentDbDistributionController::class, 'claimAssignments']);
        Route::get('/claim-request-files/{id}/download', [AgentDbDistributionController::class, 'downloadClaimFile']);

        // 설계사 - CODEF 보험/건강 조회
        Route::prefix('codef')->group(function () {
            Route::get('/customers', [AgentCodefController::class, 'customerSyncStatus']);

            Route::prefix('{customerId}')->group(function () {
                // 보험 계약
                Route::get('/insurance', [AgentCodefController::class, 'getInsuranceContracts']);
                Route::get('/insurance/{insuranceId}', [AgentCodefController::class, 'getInsuranceDetail']);
                Route::post('/insurance/fetch', [AgentCodefController::class, 'fetchInsurance']);
                Route::post('/insurance/confirm', [AgentCodefController::class, 'confirmInsurance']);

                // 진료 내역
                Route::get('/medical', [AgentCodefController::class, 'getMedicalRecords']);
                Route::post('/medical/fetch', [AgentCodefController::class, 'fetchMedical']);
                Route::post('/medical/confirm', [AgentCodefController::class, 'confirmMedical']);

                // 건강검진
                Route::get('/checkup', [AgentCodefController::class, 'getCheckups']);
                Route::post('/checkup/fetch', [AgentCodefController::class, 'fetchCheckup']);
                Route::post('/checkup/confirm', [AgentCodefController::class, 'confirmCheckup']);

                // 건강나이
                Route::get('/health-age', [AgentCodefController::class, 'getHealthAge']);
                Route::post('/health-age/fetch', [AgentCodefController::class, 'fetchHealthAge']);
                Route::post('/health-age/confirm', [AgentCodefController::class, 'confirmHealthAge']);
            });
        });
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

    // 고객 - 상담 요청 (SFR-008, SFR-017)
    Route::prefix('consultations')->middleware('role:CUSTOMER')->group(function () {
        Route::get('/', [CustomerConsultationController::class, 'index']);
        Route::post('/', [CustomerConsultationController::class, 'store']);
        Route::get('/{id}', [CustomerConsultationController::class, 'show']);
    });

    // 고객 - FCM 토큰 등록/삭제
    Route::middleware('role:CUSTOMER')->group(function () {
        Route::post('/fcm-token', [FcmTokenController::class, 'store']);
        Route::delete('/fcm-token', [FcmTokenController::class, 'destroy']);
    });

    // 고객 - 보험정보 조회 (DB)
    Route::prefix('insurances')->middleware('role:CUSTOMER')->group(function () {
        Route::get('/statistics', [InsuranceController::class, 'statistics']);
        Route::get('/', [InsuranceController::class, 'index']);
        Route::get('/{id}', [InsuranceController::class, 'show']);
    });

    // 고객 - 내보험다보여 (Credit4U) 연동
    Route::prefix('credit4u')->middleware('role:CUSTOMER')->group(function () {
        Route::post('/consent', [Credit4uController::class, 'consent']);
        Route::post('/check-registration', [Credit4uController::class, 'checkRegistration']);
        Route::post('/register', [Credit4uController::class, 'register']);
        Route::post('/fetch-contracts', [Credit4uController::class, 'fetchContracts']);
        Route::post('/find-id', [Credit4uController::class, 'findId']);
        Route::post('/change-password', [Credit4uController::class, 'changePassword']);
        Route::post('/2way-confirm', [Credit4uController::class, 'twoWayConfirm']);
    });

    // 고객 - 건강 (NHIS 건강검진 + HIRA 내진료정보)
    Route::prefix('health')->middleware('role:CUSTOMER')->group(function () {
        // 동의 관리
        Route::get('/consent/status', [HealthConsentController::class, 'status']);
        Route::post('/consent/agree', [HealthConsentController::class, 'agree']);
        Route::post('/consent/revoke', [HealthConsentController::class, 'revoke']);

        // NHIS 건강검진결과
        Route::post('/checkup/request', [HealthCheckupController::class, 'request']);
        Route::post('/checkup/confirm', [HealthCheckupController::class, 'confirm']);
        Route::get('/checkup', [HealthCheckupController::class, 'index']);
        Route::get('/checkup/latest', [HealthCheckupController::class, 'latest']);

        // HIRA 내진료정보열람
        Route::post('/medical-info/request', [HiraMedicalInfoController::class, 'request']);
        Route::post('/medical-info/confirm', [HiraMedicalInfoController::class, 'confirm']);
        Route::get('/medical-info', [HiraMedicalInfoController::class, 'index']);
        Route::get('/medical-info/summary', [HiraMedicalInfoController::class, 'summary']);

        // 검진대상 (Phase 2)
        Route::post('/examination/request', [HealthExaminationController::class, 'request']);
        Route::post('/examination/confirm', [HealthExaminationController::class, 'confirm']);
        Route::get('/examination', [HealthExaminationController::class, 'latest']);

        // 건강나이 (Phase 2)
        Route::post('/health-age/request', [HealthAgeController::class, 'request']);
        Route::post('/health-age/confirm', [HealthAgeController::class, 'confirm']);
        Route::get('/health-age', [HealthAgeController::class, 'latest']);

        // 예측 5종 (Phase 2)
        Route::post('/prediction/{type}/request', [HealthPredictionController::class, 'request'])
            ->where('type', 'cardio|stroke|diabetes|kidney|mi');
        Route::post('/prediction/{type}/confirm', [HealthPredictionController::class, 'confirm'])
            ->where('type', 'cardio|stroke|diabetes|kidney|mi');
        Route::get('/prediction', [HealthPredictionController::class, 'all']);
        Route::get('/prediction/{type}', [HealthPredictionController::class, 'latest'])
            ->where('type', 'cardio|stroke|diabetes|kidney|mi');

        // 배치: 건강나이 + 예측 5종 (1회 인증)
        Route::post('/predictions/batch/request', [HealthPredictionBatchController::class, 'request']);
        Route::post('/predictions/batch/confirm', [HealthPredictionBatchController::class, 'confirm']);

        // 종합 요약 (대시보드용)
        Route::get('/summary', [HealthSummaryController::class, 'summary']);
    });
});
