<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HealthPrediction;
use App\Services\Codef\NhisPredictionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 건강나이 (NHIS) 컨트롤러
 *
 * 건강iN 연동 상태:
 * - 최초 1회: type="0" + userId/userPassword (건강iN 별도 계정)
 * - 이후: type="1"
 * - health_external_account.health_in_linked 로 추적
 */
class HealthAgeController extends Controller
{
    protected NhisPredictionService $predictionService;

    public function __construct(NhisPredictionService $predictionService)
    {
        $this->predictionService = $predictionService;
    }

    /**
     * 건강나이 조회 요청
     * POST /api/health/health-age/request
     */
    public function request(Request $request): JsonResponse
    {
        $customer = $request->user()->customer;

        if (!$customer->healthExternalAccount?->checkup_consent_at) {
            return response()->json([
                'success' => false,
                'code' => 'NO_CONSENT',
                'message' => '건강검진 정보 조회 동의가 필요합니다.',
            ], 403);
        }

        $validated = $request->validate([
            'loginTypeLevel' => 'required|string|in:1,3,4,5,6,7,8,10',
            'userName' => 'required|string|max:50',
            'birthDate' => 'required|string|regex:/^\d{8}$/',
            'phoneNo' => 'required|string',
            'telecom' => 'required|string|in:0,1,2',
            'type' => 'nullable|string|in:0,1',
            'date' => 'nullable|string',
            'userId' => 'nullable|string',
            'userPassword' => 'nullable|string',
        ]);

        // 항상 type="0"(신규사용자) — type="1"은 건강iN userId/userPassword 필요하므로 미지원
        $linkType = '0';

        try {
            $result = $this->predictionService->fetchHealthAge([
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
            return $this->saveAndRespond($customer->customer_id, $result['data']);
        }

        return $this->buildResponse($result);
    }

    /**
     * 건강나이 2-Way 확인
     * POST /api/health/health-age/confirm
     */
    public function confirm(Request $request): JsonResponse
    {
        $customer = $request->user()->customer;

        $result = $this->predictionService->confirmHealthAge(
            $request->all(),
            $customer->customer_id
        );

        if ($result['success'] && !empty($result['data'])) {
            return $this->saveAndRespond($customer->customer_id, $result['data']);
        }

        return $this->buildResponse($result);
    }

    /**
     * 가장 최근 건강나이 조회
     * GET /api/health/health-age
     */
    public function latest(Request $request): JsonResponse
    {
        $customerId = $request->user()->customer->customer_id;

        $row = HealthPrediction::forCustomer($customerId)
            ->ofType(HealthPrediction::TYPE_HEALTH_AGE)
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'data' => $row,
        ]);
    }

    private function saveAndRespond(string $customerId, array $data): JsonResponse
    {
        try {
            $row = $this->predictionService->storeHealthAge($customerId, $data);
            return response()->json([
                'success' => true,
                'data' => $row,
            ]);
        } catch (\Exception $e) {
            Log::error('건강나이 저장 실패', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'code' => 'SAVE_ERROR',
                'message' => '건강나이 저장 중 오류가 발생했습니다.',
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
