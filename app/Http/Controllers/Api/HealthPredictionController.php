<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HealthPrediction;
use App\Services\Codef\NhisPredictionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 질환 예측 5종 컨트롤러 (cardio/stroke/diabetes/kidney/mi)
 *
 * 라우트: /api/health/prediction/{type}/request
 *         /api/health/prediction/{type}/confirm
 *         /api/health/prediction/{type}
 *         /api/health/prediction (전체)
 */
class HealthPredictionController extends Controller
{
    protected NhisPredictionService $predictionService;

    public function __construct(NhisPredictionService $predictionService)
    {
        $this->predictionService = $predictionService;
    }

    /**
     * 예측 조회 요청 (1-Way)
     * POST /api/health/prediction/{type}/request
     */
    public function request(Request $request, string $type): JsonResponse
    {
        $customer = $request->user()->customer;

        if (!$customer->healthExternalAccount?->checkup_consent_at) {
            return response()->json([
                'success' => false,
                'code' => 'NO_CONSENT',
                'message' => '건강검진 정보 조회 동의가 필요합니다.',
            ], 403);
        }

        if (!in_array($type, HealthPrediction::PREDICTION_TYPES, true)) {
            return response()->json([
                'success' => false,
                'code' => 'INVALID_TYPE',
                'message' => '유효하지 않은 예측 유형입니다.',
            ], 422);
        }

        $validated = $request->validate([
            'loginTypeLevel' => 'required|string|in:1,3,4,5,6,7,8,10',
            'userName' => 'required|string|max:50',
            'birthDate' => 'required|string|regex:/^\d{8}$/',
            'phoneNo' => 'required|string',
            'telecom' => 'required|string|in:0,1,2',
            'type' => 'nullable|string|in:0,1', // 건강iN 연동 type
            'date' => 'nullable|string',
            'userId' => 'nullable|string',
            'userPassword' => 'nullable|string',
        ]);

        // 항상 type="0"(신규사용자) — type="1"은 건강iN userId/userPassword 필요하므로 미지원
        $linkType = '0';

        try {
            $result = $this->predictionService->fetchPrediction($type, [
                'customer' => $customer,
                'customer_id' => $customer->customer_id,
                'loginTypeLevel' => $validated['loginTypeLevel'],
                'userName' => $validated['userName'],
                'birthDate' => $validated['birthDate'],
                'phoneNo' => $validated['phoneNo'],
                'telecom' => $validated['telecom'],
                'type' => $linkType,
                'date' => $validated['date'] ?? null,
                'userId' => $validated['userId'] ?? null,
                'userPassword' => $validated['userPassword'] ?? null,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'code' => 'MISSING_CUSTOMER_INFO',
                'message' => $e->getMessage(),
            ], 422);
        }

        if ($result['two_way'] ?? false) {
            return $this->buildResponse($result);
        }

        if ($result['success'] && !empty($result['data'])) {
            return $this->saveAndRespond($customer->customer_id, $type, $result['data']);
        }

        return $this->buildResponse($result);
    }

    /**
     * 예측 2-Way 확인
     * POST /api/health/prediction/{type}/confirm
     */
    public function confirm(Request $request, string $type): JsonResponse
    {
        $customer = $request->user()->customer;

        if (!in_array($type, HealthPrediction::PREDICTION_TYPES, true)) {
            return response()->json([
                'success' => false,
                'code' => 'INVALID_TYPE',
                'message' => '유효하지 않은 예측 유형입니다.',
            ], 422);
        }

        $result = $this->predictionService->confirmPrediction($type, $request->all(), $customer->customer_id);

        if ($result['success'] && !empty($result['data'])) {
            return $this->saveAndRespond($customer->customer_id, $type, $result['data']);
        }

        return $this->buildResponse($result);
    }

    /**
     * 가장 최근 단일 예측 조회
     * GET /api/health/prediction/{type}
     */
    public function latest(Request $request, string $type): JsonResponse
    {
        if (!in_array($type, HealthPrediction::PREDICTION_TYPES, true)) {
            return response()->json([
                'success' => false,
                'code' => 'INVALID_TYPE',
                'message' => '유효하지 않은 예측 유형입니다.',
            ], 422);
        }

        $customerId = $request->user()->customer->customer_id;

        $row = HealthPrediction::forCustomer($customerId)
            ->ofType($type)
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'data' => $row,
        ]);
    }

    /**
     * 저장된 모든 예측 조회 (5종 + 건강나이)
     * GET /api/health/prediction
     */
    public function all(Request $request): JsonResponse
    {
        $customerId = $request->user()->customer->customer_id;

        $rows = HealthPrediction::forCustomer($customerId)
            ->orderByDesc('predicted_at')
            ->get()
            ->groupBy('prediction_type')
            ->map(fn ($group) => $group->first());

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    private function saveAndRespond(string $customerId, string $type, array $data): JsonResponse
    {
        try {
            $row = $this->predictionService->storePrediction($customerId, $type, $data);
            return response()->json([
                'success' => true,
                'data' => $row,
            ]);
        } catch (\Exception $e) {
            Log::error('예측 저장 실패', [
                'customer_id' => $customerId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'code' => 'SAVE_ERROR',
                'message' => '예측 결과 저장 중 오류가 발생했습니다.',
            ], 500);
        }
    }

    private function buildResponse(array $result): JsonResponse
    {
        $statusCode = ($result['success'] ?? false) ? 200 : 422;
        if (($result['two_way'] ?? false)) $statusCode = 200;

        return response()->json([
            'success' => $result['success'] ?? false,
            'code' => $result['code'] ?? null,
            'message' => $result['message'] ?? '',
            'data' => $result['data'] ?? null,
            'two_way' => $result['two_way'] ?? false,
            'extraInfo' => $result['extraInfo'] ?? null,
        ], $statusCode);
    }
}
