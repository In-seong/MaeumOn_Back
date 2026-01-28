<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\InsuranceCompanyController;
use App\Http\Controllers\Api\Admin\ClaimFormTemplateController;
use App\Http\Controllers\Api\Admin\TemplateFieldController;
use App\Http\Controllers\Api\Admin\TemplatePageController;
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

// 공개 API (인증 불필요)
Route::get('/insurance-companies', [InsuranceCompanyController::class, 'publicIndex']);
Route::get('/templates', [ClaimFormTemplateController::class, 'publicIndex']);
Route::get('/templates/{id}', [ClaimFormTemplateController::class, 'publicShow']);

// 인증 필요 API
Route::middleware('auth:sanctum')->group(function () {

    // 관리자 API
    Route::prefix('admin')->group(function () {
        // 보험사 관리
        Route::apiResource('insurance-companies', InsuranceCompanyController::class);

        // 양식 템플릿 관리
        Route::apiResource('templates', ClaimFormTemplateController::class);
        Route::post('/templates/{id}/upload-image', [ClaimFormTemplateController::class, 'uploadImage']);

        // 페이지 관리
        Route::get('/templates/{templateId}/pages', [TemplatePageController::class, 'index']);
        Route::post('/templates/{templateId}/pages', [TemplatePageController::class, 'store']);
        Route::get('/templates/{templateId}/pages/{pageId}', [TemplatePageController::class, 'show']);
        Route::put('/templates/{templateId}/pages/{pageId}', [TemplatePageController::class, 'update']);
        Route::delete('/templates/{templateId}/pages/{pageId}', [TemplatePageController::class, 'destroy']);
        Route::post('/templates/{templateId}/pages/{pageId}/upload-image', [TemplatePageController::class, 'uploadImage']);
        Route::put('/templates/{templateId}/pages/reorder', [TemplatePageController::class, 'reorder']);

        // 필드 관리
        Route::get('/templates/{templateId}/fields', [TemplateFieldController::class, 'index']);
        Route::post('/templates/{templateId}/fields', [TemplateFieldController::class, 'store']);
        Route::put('/templates/{templateId}/fields/bulk-update', [TemplateFieldController::class, 'bulkUpdate']);
        Route::get('/pages/{pageId}/fields', [TemplateFieldController::class, 'indexByPage']);
        Route::post('/pages/{pageId}/fields', [TemplateFieldController::class, 'storeToPage']);
        Route::put('/fields/{id}', [TemplateFieldController::class, 'update']);
        Route::delete('/fields/{id}', [TemplateFieldController::class, 'destroy']);

        // 청구 관리 (관리자용)
        Route::get('/claims', [ClaimController::class, 'adminIndex']);
        Route::put('/claims/{id}/status', [ClaimController::class, 'updateStatus']);
    });

    // 고객 API
    Route::prefix('claims')->group(function () {
        Route::get('/', [ClaimController::class, 'index']);
        Route::post('/', [ClaimController::class, 'store']);
        Route::get('/{id}', [ClaimController::class, 'show']);
        Route::get('/{id}/download/image', [ClaimController::class, 'downloadImage']);
        Route::get('/{id}/download/pdf', [ClaimController::class, 'downloadPdf']);
        Route::post('/{id}/send-fax', [ClaimController::class, 'sendFax']);
    });
});
