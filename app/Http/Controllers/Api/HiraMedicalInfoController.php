<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MedicalRecord;
use App\Services\Codef\HiraMedicalInfoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * HIRA 내진료정보열람 컨트롤러
 *
 * ⚠️ IP 차단 주의:
 * - 24h 쿨다운 강제 (isRateLimited)
 * - 호출 전 주민번호 검증 (HiraMedicalInfoService::validateIdentity)
 */
class HiraMedicalInfoController extends Controller
{
    protected HiraMedicalInfoService $hiraService;

    public function __construct(HiraMedicalInfoService $hiraService)
    {
        $this->hiraService = $hiraService;
    }

    /**
     * 내진료정보 조회 요청 (1-Way)
     * POST /api/health/medical-info/request
     */
    public function request(Request $request): JsonResponse
    {
        $customer = $request->user()->customer;

        // 1. 동의 확인
        if (!$customer->healthExternalAccount?->medical_info_consent_at) {
            return response()->json([
                'success' => false,
                'code' => 'NO_CONSENT',
                'message' => '내진료정보열람 동의가 필요합니다.',
            ], 403);
        }

        // 2. 쿨다운 체크 (IP 차단 방지)
        if ($this->hiraService->isRateLimited($customer->customer_id)) {
            return response()->json([
                'success' => false,
                'code' => 'RATE_LIMITED',
                'message' => '24시간 이내 동기화 이력이 있어 재조회할 수 없습니다.',
            ], 429);
        }

        // 3. 입력 검증 (HIRA: identity=주민번호 13자리 필수)
        $validated = $request->validate([
            'loginTypeLevel' => 'required|string|in:1,3,4,5,6,7,8,10',
            'userName' => 'required|string|max:50',
            'identity' => 'required|string|regex:/^\d{13}$/',
            'phoneNo' => 'required|string',
            'telecom' => 'required|string|in:0,1,2',
            'startDate' => 'nullable|string',
            'endDate' => 'nullable|string',
            'includeSensitive' => 'nullable|boolean',
        ]);

        // 기본 5년 범위
        $defaults = HiraMedicalInfoService::defaultDateRange();
        $startDate = $validated['startDate'] ?? $defaults['start'];
        $endDate = $validated['endDate'] ?? $defaults['end'];
        $includeSensitive = $validated['includeSensitive'] ?? true;

        try {
            $result = $this->hiraService->fetchMedicalInfo(
                [
                    'customer' => $customer,
                    'customer_id' => $customer->customer_id,
                    'loginTypeLevel' => $validated['loginTypeLevel'],
                    'userName' => $validated['userName'],
                    'identity' => $validated['identity'],
                    'phoneNo' => $validated['phoneNo'],
                    'telecom' => $validated['telecom'],
                ],
                $startDate,
                $endDate,
                $includeSensitive
            );
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'code' => 'MISSING_CUSTOMER_INFO',
                'message' => $e->getMessage(),
            ], 422);
        }

        // 2-Way 응답
        if ($result['two_way'] ?? false) {
            // 날짜 범위는 다음 단계에서도 필요하므로 캐시에 저장 (apiService 컨텍스트에 이미 포함됨)
            return $this->buildResponse($result);
        }

        // 1-Way 성공 → DB 저장
        if ($result['success'] && !empty($result['data'])) {
            return $this->saveAndRespond($customer->customer_id, $result['data'], $startDate, $endDate);
        }

        return $this->buildResponse($result);
    }

    /**
     * 2-Way 추가인증 확인
     * POST /api/health/medical-info/confirm
     */
    public function confirm(Request $request): JsonResponse
    {
        $customer = $request->user()->customer;

        $validated = $request->validate([
            'startDate' => 'nullable|string',
            'endDate' => 'nullable|string',
        ]);

        $defaults = HiraMedicalInfoService::defaultDateRange();
        $startDate = $validated['startDate'] ?? $defaults['start'];
        $endDate = $validated['endDate'] ?? $defaults['end'];

        // 캐시 컨텍스트에 startDate/endDate가 이미 들어있으므로 별도 전달 불필요
        $twoWayInput = $request->except(['startDate', 'endDate']);

        $result = $this->hiraService->confirmMedicalInfo(
            $twoWayInput,
            $customer->customer_id
        );

        if ($result['success'] && !empty($result['data'])) {
            return $this->saveAndRespond($customer->customer_id, $result['data'], $startDate, $endDate);
        }

        return $this->buildResponse($result);
    }

    /**
     * 저장된 진료내역 목록 (HIRA + manual)
     * GET /api/health/medical-info?source=codef_hira|manual|all
     */
    public function index(Request $request): JsonResponse
    {
        $customerId = $request->user()->customer->customer_id;
        $source = $request->query('source', 'all');

        $query = MedicalRecord::forCustomer($customerId)
            ->orderByDesc('treatment_date')
            ->orderByDesc('record_id');

        if ($source === 'codef_hira') {
            $query->fromHira();
        } elseif ($source === 'manual') {
            $query->manual();
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    /**
     * 진단명/진단과 집계
     * GET /api/health/medical-info/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $customerId = $request->user()->customer->customer_id;

        $records = MedicalRecord::forCustomer($customerId)->fromHira()->get();

        $byDisease = [];
        $byDepartment = [];

        foreach ($records as $r) {
            if ($r->diagnosis_name) {
                $key = $r->diagnosis_name;
                $byDisease[$key] = ($byDisease[$key] ?? 0) + 1;
            }
            if ($r->department) {
                $key = $r->department;
                $byDepartment[$key] = ($byDepartment[$key] ?? 0) + 1;
            }
        }

        arsort($byDisease);
        arsort($byDepartment);

        return response()->json([
            'success' => true,
            'data' => [
                'total_records' => $records->count(),
                'by_disease' => $byDisease,
                'by_department' => $byDepartment,
            ],
        ]);
    }

    /**
     * 저장 + 응답 헬퍼
     */
    private function saveAndRespond(string $customerId, array $data, string $startDate, string $endDate): JsonResponse
    {
        try {
            $count = $this->hiraService->storeMedicalInfo($customerId, $data, $startDate, $endDate);

            return response()->json([
                'success' => true,
                'message' => '진료내역이 저장되었습니다.',
                'data' => [
                    'count' => $count,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('HIRA 진료내역 저장 실패', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'code' => 'SAVE_ERROR',
                'message' => '진료내역 저장 중 오류가 발생했습니다.',
            ], 500);
        }
    }

    /**
     * CODEF 응답 → HTTP 응답 변환
     */
    private function buildResponse(array $result): JsonResponse
    {
        $statusCode = 200;

        if (!($result['success'] ?? false)) {
            $code = $result['code'] ?? 'ERROR';

            if ($code === 'CF-03002' || ($result['two_way'] ?? false)) {
                $statusCode = 200;
            } elseif (in_array($code, ['TWO_WAY_EXPIRED'])) {
                $statusCode = 400;
            } elseif ($code === 'INVALID_IDENTITY') {
                $statusCode = 422;
            } elseif ($code === 'TIMEOUT') {
                $statusCode = 504;
            } elseif (str_starts_with($code, 'HTTP_')) {
                $statusCode = 502;
            } else {
                $statusCode = 422;
            }
        }

        $response = [
            'success' => $result['success'] ?? false,
            'code' => $result['code'] ?? null,
            'message' => $result['message'] ?? '',
        ];

        if (isset($result['data'])) {
            $response['data'] = $result['data'];
        }
        if (isset($result['two_way'])) {
            $response['two_way'] = $result['two_way'];
        }
        if (isset($result['extraInfo'])) {
            $response['extraInfo'] = $result['extraInfo'];
        }

        return response()->json($response, $statusCode);
    }
}
