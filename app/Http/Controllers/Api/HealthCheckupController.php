<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HealthCheckup;
use App\Services\Codef\NhisHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HealthCheckupController extends Controller
{
    protected NhisHealthService $nhisHealthService;

    public function __construct(NhisHealthService $nhisHealthService)
    {
        $this->nhisHealthService = $nhisHealthService;
    }

    /** POST /api/health/checkup/request */
    public function request(Request $request): JsonResponse
    {
        $customer = $request->user()->customer;

        if (!$customer->healthExternalAccount?->checkup_consent_at) {
            return response()->json([
                'success' => false, 'code' => 'NO_CONSENT',
                'message' => '건강검진 정보 조회 동의가 필요합니다.',
            ], 403);
        }

        $validated = $request->validate([
            'loginTypeLevel' => 'required|string|in:1,3,4,5,6,7,8,10',
            'userName' => 'required|string|max:50',
            'birthDate' => 'required|string|regex:/^\d{8}$/',
            'phoneNo' => 'required|string',
            'telecom' => 'required|string|in:0,1,2',
            'searchStartYear' => 'nullable|string|regex:/^\d{4}$/',
            'searchEndYear' => 'nullable|string|regex:/^\d{4}$/',
            'type' => 'nullable|string|in:0,1,2',
        ]);

        try {
            $result = $this->nhisHealthService->fetchCheckupResult([
                'customer' => $customer,
                'customer_id' => $customer->customer_id,
                'loginTypeLevel' => $validated['loginTypeLevel'],
                'userName' => $validated['userName'],
                'birthDate' => $validated['birthDate'],
                'phoneNo' => $validated['phoneNo'],
                'telecom' => $validated['telecom'],
                'searchStartYear' => $validated['searchStartYear'] ?? null,
                'searchEndYear' => $validated['searchEndYear'] ?? null,
                'type' => $validated['type'] ?? '0',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false, 'code' => 'MISSING_CUSTOMER_INFO',
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

    /** POST /api/health/checkup/confirm */
    public function confirm(Request $request): JsonResponse
    {
        $customer = $request->user()->customer;

        $result = $this->nhisHealthService->confirmCheckupResult(
            $request->all(),
            $customer->customer_id
        );

        if ($result['success'] && !empty($result['data'])) {
            return $this->saveAndRespond($customer->customer_id, $result['data']);
        }

        return $this->buildResponse($result);
    }

    /** GET /api/health/checkup */
    public function index(Request $request): JsonResponse
    {
        $customerId = $request->user()->customer->customer_id;
        return response()->json([
            'success' => true,
            'data' => HealthCheckup::latestForCustomer($customerId)->get(),
        ]);
    }

    /** GET /api/health/checkup/latest */
    public function latest(Request $request): JsonResponse
    {
        $customerId = $request->user()->customer->customer_id;
        return response()->json([
            'success' => true,
            'data' => HealthCheckup::latestForCustomer($customerId)->first(),
        ]);
    }

    private function saveAndRespond(string $customerId, array $data): JsonResponse
    {
        try {
            $saved = $this->nhisHealthService->storeCheckupResult($customerId, $data);
        } catch (\Exception $e) {
            Log::error('NHIS 검진결과 저장 실패', [
                'customer_id' => $customerId, 'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false, 'code' => 'SAVE_ERROR',
                'message' => '검진결과 저장 중 오류가 발생했습니다.',
            ], 500);
        }

        if (count($saved) === 0) {
            return response()->json([
                'success' => true, 'code' => 'NO_RECORDS',
                'message' => '최근 10년 내 건강검진 이력이 없습니다.',
                'data' => ['count' => 0, 'checkups' => []],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => '건강검진결과가 저장되었습니다.',
            'data' => ['count' => count($saved), 'checkups' => $saved],
        ]);
    }

    private function buildResponse(array $result): JsonResponse
    {
        $statusCode = ($result['success'] ?? false) ? 200 : 422;
        if (($result['two_way'] ?? false) || ($result['code'] ?? '') === 'CF-03002') $statusCode = 200;
        if (($result['code'] ?? '') === 'TWO_WAY_EXPIRED') $statusCode = 400;
        if (($result['code'] ?? '') === 'TIMEOUT') $statusCode = 504;

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
