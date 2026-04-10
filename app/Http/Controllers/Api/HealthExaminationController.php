<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HealthExternalAccount;
use App\Services\Codef\NhisHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 검진대상 (NHIS) — 올해 건강검진/암검진 대상 여부 조회
 *
 * 응답이 비교적 단순하고 변경이 드물어 캐시(year 기준)에 저장.
 */
class HealthExaminationController extends Controller
{
    protected NhisHealthService $nhisHealthService;

    public function __construct(NhisHealthService $nhisHealthService)
    {
        $this->nhisHealthService = $nhisHealthService;
    }

    /**
     * 검진대상 조회 요청 (1-Way)
     * POST /api/health/examination/request
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
        ]);

        try {
            $result = $this->nhisHealthService->fetchExamination([
                'customer' => $customer,
                'customer_id' => $customer->customer_id,
                'loginTypeLevel' => $validated['loginTypeLevel'],
                'userName' => $validated['userName'],
                'birthDate' => $validated['birthDate'],
                'phoneNo' => $validated['phoneNo'],
                'telecom' => $validated['telecom'],
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
            $this->cacheExamination($customer->customer_id, $result['data']);

            return response()->json([
                'success' => true,
                'data' => $result['data'],
            ]);
        }

        return $this->buildResponse($result);
    }

    /**
     * 검진대상 2-Way 확인
     * POST /api/health/examination/confirm
     */
    public function confirm(Request $request): JsonResponse
    {
        $customer = $request->user()->customer;

        $result = $this->nhisHealthService->confirmExamination(
            $request->all(),
            $customer->customer_id
        );

        if ($result['success'] && !empty($result['data'])) {
            $this->cacheExamination($customer->customer_id, $result['data']);

            return response()->json([
                'success' => true,
                'data' => $result['data'],
            ]);
        }

        return $this->buildResponse($result);
    }

    /**
     * 캐시된 최근 검진대상 조회
     * GET /api/health/examination
     */
    public function latest(Request $request): JsonResponse
    {
        $customerId = $request->user()->customer->customer_id;
        $year = (string) now()->year;

        $cached = Cache::get($this->cacheKey($customerId, $year));

        return response()->json([
            'success' => true,
            'data' => $cached,
        ]);
    }

    private function cacheExamination(string $customerId, array $data): void
    {
        $year = (string) now()->year;
        // 1년 캐시
        Cache::put($this->cacheKey($customerId, $year), $data, now()->addYear());

        try {
            HealthExternalAccount::updateOrCreate(
                ['customer_id' => $customerId],
                ['last_examination_sync_at' => now()]
            );
        } catch (\Exception $e) {
            Log::error('검진대상 동기화 시각 갱신 실패', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function cacheKey(string $customerId, string $year): string
    {
        return "health_examination:{$customerId}:{$year}";
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
