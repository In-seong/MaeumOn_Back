<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\CodefApiLog;
use App\Models\Customer;
use App\Models\HealthCheckup;
use App\Models\HealthExternalAccount;
use App\Models\HealthPrediction;
use App\Models\Insurance;
use App\Models\MedicalRecord;
use App\Services\Codef\Credit4uService;
use App\Services\Codef\HiraMedicalInfoService;
use App\Services\Codef\NhisHealthService;
use App\Services\Codef\NhisPredictionService;
use App\Services\Insurance\InsuranceContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 설계사(Agent)용 CODEF 보험/건강 데이터 조회 컨트롤러
 *
 * 설계사가 자기 담당 고객의 CODEF 데이터를 조회할 수 있도록 한다.
 * 기존 서비스 레이어(Credit4uService, HiraMedicalInfoService 등)를 그대로 재사용하며,
 * 매 요청에서 해당 고객이 설계사 소속인지 검증한다.
 */
class AgentCodefController extends Controller
{
    protected Credit4uService $credit4uService;
    protected InsuranceContractService $contractService;
    protected HiraMedicalInfoService $hiraService;
    protected NhisHealthService $nhisHealthService;
    protected NhisPredictionService $predictionService;

    public function __construct(
        Credit4uService $credit4uService,
        InsuranceContractService $contractService,
        HiraMedicalInfoService $hiraService,
        NhisHealthService $nhisHealthService,
        NhisPredictionService $predictionService
    ) {
        $this->credit4uService = $credit4uService;
        $this->contractService = $contractService;
        $this->hiraService = $hiraService;
        $this->nhisHealthService = $nhisHealthService;
        $this->predictionService = $predictionService;
    }

    // ========================================================================
    // E. 고객 목록 + 동기화 상태
    // ========================================================================

    /**
     * 설계사 담당 고객 리스트 + 각 고객별 CODEF 동기화 상태 요약
     * GET /api/agent/codef/customers
     */
    public function customerSyncStatus(Request $request): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $query = Customer::where('agent_id', $agentId)
            ->where('is_active', true);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderBy('name')->get();

        $customerIds = $customers->pluck('customer_id')->toArray();

        // 보험 동기화 시각: insurance 테이블에서 codef_synced=true인 최신 synced_at
        $insuranceSynced = Insurance::whereIn('customer_id', $customerIds)
            ->where('codef_synced', true)
            ->selectRaw('customer_id, MAX(synced_at) as latest_synced_at')
            ->groupBy('customer_id')
            ->pluck('latest_synced_at', 'customer_id');

        // health_external_account 동기화 시각
        $healthAccounts = HealthExternalAccount::whereIn('customer_id', $customerIds)
            ->get()
            ->keyBy('customer_id');

        // 건강나이 동기화 시각: health_prediction의 prediction_type=health_age 최신 created_at
        $healthAgeSynced = HealthPrediction::whereIn('customer_id', $customerIds)
            ->where('prediction_type', HealthPrediction::TYPE_HEALTH_AGE)
            ->selectRaw('customer_id, MAX(created_at) as latest_created_at')
            ->groupBy('customer_id')
            ->pluck('latest_created_at', 'customer_id');

        $data = $customers->map(function ($customer) use ($insuranceSynced, $healthAccounts, $healthAgeSynced) {
            $customerId = $customer->customer_id;
            $healthAccount = $healthAccounts->get($customerId);

            return [
                'customer_id' => $customerId,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'insurance_synced_at' => $insuranceSynced->get($customerId),
                'medical_synced_at' => $healthAccount?->last_medical_info_sync_at,
                'checkup_synced_at' => $healthAccount?->last_checkup_sync_at,
                'health_age_synced_at' => $healthAgeSynced->get($customerId),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    // ========================================================================
    // A. 보험 계약 (Credit4u)
    // ========================================================================

    /**
     * DB에 저장된 보험 목록 조회
     * GET /api/agent/codef/{customerId}/insurance
     */
    public function getInsuranceContracts(Request $request, string $customerId): JsonResponse
    {
        $customer = $this->resolveCustomer($request, $customerId);

        $contracts = $this->contractService->getCustomerContracts($customer->customer_id);

        return response()->json([
            'success' => true,
            'data' => $contracts,
        ]);
    }

    /**
     * 보험 상세 + 보장내역
     * GET /api/agent/codef/{customerId}/insurance/{insuranceId}
     */
    public function getInsuranceDetail(Request $request, string $customerId, int $insuranceId): JsonResponse
    {
        $customer = $this->resolveCustomer($request, $customerId);

        // 해당 보험이 고객 소유인지 검증
        $insurance = Insurance::where('insurance_id', $insuranceId)
            ->where('customer_id', $customer->customer_id)
            ->firstOrFail();

        $detail = $this->contractService->getContractDetail($insurance->insurance_id);

        return response()->json([
            'success' => true,
            'data' => $detail,
        ]);
    }

    /**
     * Credit4u API로 계약조회 실행
     * POST /api/agent/codef/{customerId}/insurance/fetch
     */
    public function fetchInsurance(Request $request, string $customerId): JsonResponse
    {
        $customer = $this->resolveCustomer($request, $customerId);

        $validated = $request->validate([
            'id' => 'required|string',
            'password' => 'required|string',
        ]);

        $loginData = [
            'id' => $validated['id'],
            'password' => $validated['password'],
            'customer_id' => $customer->customer_id,
        ];

        $agentId = $request->user()->agent->agent_id;
        $result = $this->credit4uService->getContractInfo($loginData);

        // 2-Way 발생 시
        if ($result['two_way'] ?? false) {
            $this->logApiCall($agentId, $customer->customer_id, CodefApiLog::TYPE_INSURANCE, CodefApiLog::ACTION_FETCH, CodefApiLog::STATUS_TWO_WAY);
            return $this->buildResponse($result);
        }

        // 성공 시 DB에 저장
        if ($result['success'] && !empty($result['data'])) {
            try {
                $this->contractService->syncContracts($customer->customer_id, $result['data']);

                $contracts = $this->contractService->getCustomerContracts($customer->customer_id);

                $this->logApiCall($agentId, $customer->customer_id, CodefApiLog::TYPE_INSURANCE, CodefApiLog::ACTION_FETCH, CodefApiLog::STATUS_SUCCESS, count($result['data']));

                return response()->json([
                    'success' => true,
                    'data' => $contracts,
                    'message' => '보험 계약정보가 동기화되었습니다.',
                ]);
            } catch (\Exception $e) {
                Log::error('설계사 보험 계약정보 DB 저장 실패', [
                    'customer_id' => $customer->customer_id,
                    'error' => $e->getMessage(),
                ]);

                $this->logApiCall($agentId, $customer->customer_id, CodefApiLog::TYPE_INSURANCE, CodefApiLog::ACTION_FETCH, CodefApiLog::STATUS_FAILED, 0, $e->getMessage());

                return response()->json([
                    'success' => false,
                    'message' => '계약정보 저장 중 오류가 발생했습니다.',
                ], 500);
            }
        }

        $this->logApiCall($agentId, $customer->customer_id, CodefApiLog::TYPE_INSURANCE, CodefApiLog::ACTION_FETCH, CodefApiLog::STATUS_FAILED, 0, $result['message'] ?? null);

        return $this->buildResponse($result);
    }

    /**
     * 보험 2-Way 확인
     * POST /api/agent/codef/{customerId}/insurance/confirm
     */
    public function confirmInsurance(Request $request, string $customerId): JsonResponse
    {
        $customer = $this->resolveCustomer($request, $customerId);
        $agentId = $request->user()->agent->agent_id;

        $twoWayInput = $request->all();

        $result = $this->credit4uService->getContractInfoConfirm($twoWayInput, $customer->customer_id);

        // 성공 시 DB에 저장
        if ($result['success'] && !empty($result['data'])) {
            try {
                $this->contractService->syncContracts($customer->customer_id, $result['data']);

                $contracts = $this->contractService->getCustomerContracts($customer->customer_id);

                $this->logApiCall($agentId, $customer->customer_id, CodefApiLog::TYPE_INSURANCE, CodefApiLog::ACTION_CONFIRM, CodefApiLog::STATUS_SUCCESS, count($result['data']));

                return response()->json([
                    'success' => true,
                    'data' => $contracts,
                    'message' => '보험 계약정보가 동기화되었습니다.',
                ]);
            } catch (\Exception $e) {
                Log::error('설계사 2-Way 후 계약정보 DB 저장 실패', [
                    'customer_id' => $customer->customer_id,
                    'error' => $e->getMessage(),
                ]);

                $this->logApiCall($agentId, $customer->customer_id, CodefApiLog::TYPE_INSURANCE, CodefApiLog::ACTION_CONFIRM, CodefApiLog::STATUS_FAILED, 0, $e->getMessage());

                return response()->json([
                    'success' => false,
                    'message' => '계약정보 저장 중 오류가 발생했습니다.',
                ], 500);
            }
        }

        $this->logApiCall($agentId, $customer->customer_id, CodefApiLog::TYPE_INSURANCE, CodefApiLog::ACTION_CONFIRM, CodefApiLog::STATUS_FAILED, 0, $result['message'] ?? null);

        return $this->buildResponse($result);
    }

    // ========================================================================
    // B. 진료 내역 (HIRA)
    // ========================================================================

    /**
     * DB에 저장된 진료내역 조회
     * GET /api/agent/codef/{customerId}/medical
     */
    public function getMedicalRecords(Request $request, string $customerId): JsonResponse
    {
        $customer = $this->resolveCustomer($request, $customerId);

        $records = MedicalRecord::where('customer_id', $customer->customer_id)
            ->orderBy('treatment_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $records,
        ]);
    }

    /**
     * HIRA API로 진료내역 조회
     * POST /api/agent/codef/{customerId}/medical/fetch
     *
     * request body: {loginTypeLevel, telecom}
     * userName, identity(주민번호), phoneNo는 Customer 모델에서 가져옴
     */
    public function fetchMedical(Request $request, string $customerId): JsonResponse
    {
        $customer = $this->resolveCustomer($request, $customerId);
        $agentId = $request->user()->agent->agent_id;

        $validated = $request->validate([
            'loginTypeLevel' => 'required|string|in:1,3,4,5,6,7,8,10',
            'telecom' => 'required|string|in:0,1,2',
            'startDate' => 'nullable|string',
            'endDate' => 'nullable|string',
            'includeSensitive' => 'nullable|boolean',
        ]);

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
                    'userName' => $customer->name,
                    'identity' => $customer->resident_number,
                    'phoneNo' => $customer->phone,
                    'telecom' => $validated['telecom'],
                ],
                $startDate,
                $endDate,
                $includeSensitive
            );
        } catch (\RuntimeException $e) {
            $this->logApiCall($agentId, $customer->customer_id, CodefApiLog::TYPE_MEDICAL, CodefApiLog::ACTION_FETCH, CodefApiLog::STATUS_FAILED, 0, $e->getMessage());
            return response()->json([
                'success' => false,
                'code' => 'MISSING_CUSTOMER_INFO',
                'message' => $e->getMessage(),
            ], 422);
        }

        if ($result['two_way'] ?? false) {
            $this->logApiCall($agentId, $customer->customer_id, CodefApiLog::TYPE_MEDICAL, CodefApiLog::ACTION_FETCH, CodefApiLog::STATUS_TWO_WAY);
            return $this->buildResponse($result);
        }

        if ($result['success'] && !empty($result['data'])) {
            $this->logApiCall($agentId, $customer->customer_id, CodefApiLog::TYPE_MEDICAL, CodefApiLog::ACTION_FETCH, CodefApiLog::STATUS_SUCCESS, is_array($result['data']) ? count($result['data']) : 0);
            return $this->saveMedicalAndRespond($customer->customer_id, $result['data'], $startDate, $endDate);
        }

        $this->logApiCall($agentId, $customer->customer_id, CodefApiLog::TYPE_MEDICAL, CodefApiLog::ACTION_FETCH, CodefApiLog::STATUS_FAILED, 0, $result['message'] ?? null);
        return $this->buildResponse($result);
    }

    /**
     * 진료 2-Way 확인
     * POST /api/agent/codef/{customerId}/medical/confirm
     */
    public function confirmMedical(Request $request, string $customerId): JsonResponse
    {
        $customer = $this->resolveCustomer($request, $customerId);
        $agentId = $request->user()->agent->agent_id;

        $validated = $request->validate([
            'startDate' => 'nullable|string',
            'endDate' => 'nullable|string',
        ]);

        $defaults = HiraMedicalInfoService::defaultDateRange();
        $startDate = $validated['startDate'] ?? $defaults['start'];
        $endDate = $validated['endDate'] ?? $defaults['end'];

        $twoWayInput = $request->except(['startDate', 'endDate']);

        $result = $this->hiraService->confirmMedicalInfo($twoWayInput, $customer->customer_id);

        if ($result['success'] && !empty($result['data'])) {
            $this->logApiCall($agentId, $customer->customer_id, CodefApiLog::TYPE_MEDICAL, CodefApiLog::ACTION_CONFIRM, CodefApiLog::STATUS_SUCCESS, is_array($result['data']) ? count($result['data']) : 0);
            return $this->saveMedicalAndRespond($customer->customer_id, $result['data'], $startDate, $endDate);
        }

        $this->logApiCall($agentId, $customer->customer_id, CodefApiLog::TYPE_MEDICAL, CodefApiLog::ACTION_CONFIRM, CodefApiLog::STATUS_FAILED, 0, $result['message'] ?? null);
        return $this->buildResponse($result);
    }

    // ========================================================================
    // C. 건강검진 (NHIS)
    // ========================================================================

    /**
     * DB에 저장된 검진결과 조회
     * GET /api/agent/codef/{customerId}/checkup
     */
    public function getCheckups(Request $request, string $customerId): JsonResponse
    {
        $customer = $this->resolveCustomer($request, $customerId);

        $checkups = HealthCheckup::where('customer_id', $customer->customer_id)
            ->orderBy('checkup_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $checkups,
        ]);
    }

    /**
     * NHIS API로 검진결과 조회
     * POST /api/agent/codef/{customerId}/checkup/fetch
     *
     * request body: {loginTypeLevel, telecom}
     * userName, birthDate, phoneNo는 Customer 모델에서 가져옴
     */
    public function fetchCheckup(Request $request, string $customerId): JsonResponse
    {
        $customer = $this->resolveCustomer($request, $customerId);

        $validated = $request->validate([
            'loginTypeLevel' => 'required|string|in:1,3,4,5,6,7,8,10',
            'telecom' => 'required|string|in:0,1,2',
            'searchStartYear' => 'nullable|string|regex:/^\d{4}$/',
            'searchEndYear' => 'nullable|string|regex:/^\d{4}$/',
        ]);

        $agentId = $request->user()->agent->agent_id;

        try {
            $result = $this->nhisHealthService->fetchCheckupResult([
                'customer' => $customer,
                'customer_id' => $customer->customer_id,
                'loginTypeLevel' => $validated['loginTypeLevel'],
                'userName' => $customer->name,
                'birthDate' => $customer->birth_date?->format('Ymd'),
                'phoneNo' => $customer->phone,
                'telecom' => $validated['telecom'],
                'searchStartYear' => $validated['searchStartYear'] ?? null,
                'searchEndYear' => $validated['searchEndYear'] ?? null,
            ]);
        } catch (\RuntimeException $e) {
            $this->logApiCall($agentId, $customer->customer_id, CodefApiLog::TYPE_CHECKUP, CodefApiLog::ACTION_FETCH, CodefApiLog::STATUS_FAILED, 0, $e->getMessage());
            return response()->json([
                'success' => false,
                'code' => 'MISSING_CUSTOMER_INFO',
                'message' => $e->getMessage(),
            ], 422);
        }

        if ($result['two_way'] ?? false) {
            $this->logApiCall($agentId, $customer->customer_id, CodefApiLog::TYPE_CHECKUP, CodefApiLog::ACTION_FETCH, CodefApiLog::STATUS_TWO_WAY);
            return $this->buildResponse($result);
        }

        if ($result['success'] && !empty($result['data'])) {
            $this->logApiCall($agentId, $customer->customer_id, CodefApiLog::TYPE_CHECKUP, CodefApiLog::ACTION_FETCH, CodefApiLog::STATUS_SUCCESS, is_array($result['data']) ? count($result['data']) : 0);
            return $this->saveCheckupAndRespond($customer->customer_id, $result['data']);
        }

        $this->logApiCall($agentId, $customer->customer_id, CodefApiLog::TYPE_CHECKUP, CodefApiLog::ACTION_FETCH, CodefApiLog::STATUS_FAILED, 0, $result['message'] ?? null);
        return $this->buildResponse($result);
    }

    /**
     * 검진 2-Way 확인
     * POST /api/agent/codef/{customerId}/checkup/confirm
     */
    public function confirmCheckup(Request $request, string $customerId): JsonResponse
    {
        $customer = $this->resolveCustomer($request, $customerId);
        $agentId = $request->user()->agent->agent_id;

        $result = $this->nhisHealthService->confirmCheckupResult(
            $request->all(),
            $customer->customer_id
        );

        if ($result['success'] && !empty($result['data'])) {
            $this->logApiCall($agentId, $customer->customer_id, CodefApiLog::TYPE_CHECKUP, CodefApiLog::ACTION_CONFIRM, CodefApiLog::STATUS_SUCCESS, is_array($result['data']) ? count($result['data']) : 0);
            return $this->saveCheckupAndRespond($customer->customer_id, $result['data']);
        }

        $this->logApiCall($agentId, $customer->customer_id, CodefApiLog::TYPE_CHECKUP, CodefApiLog::ACTION_CONFIRM, CodefApiLog::STATUS_FAILED, 0, $result['message'] ?? null);
        return $this->buildResponse($result);
    }

    // ========================================================================
    // D. 건강나이 (NHIS Prediction)
    // ========================================================================

    /**
     * DB에 저장된 건강나이 조회
     * GET /api/agent/codef/{customerId}/health-age
     */
    public function getHealthAge(Request $request, string $customerId): JsonResponse
    {
        $customer = $this->resolveCustomer($request, $customerId);

        $row = HealthPrediction::where('customer_id', $customer->customer_id)
            ->where('prediction_type', HealthPrediction::TYPE_HEALTH_AGE)
            ->orderByDesc('predicted_at')
            ->orderByDesc('prediction_id')
            ->first();

        return response()->json([
            'success' => true,
            'data' => $row,
        ]);
    }

    /**
     * 건강나이 조회 요청
     * POST /api/agent/codef/{customerId}/health-age/fetch
     *
     * request body: {loginTypeLevel, telecom}
     * userName, birthDate, phoneNo는 Customer 모델에서 가져옴
     */
    public function fetchHealthAge(Request $request, string $customerId): JsonResponse
    {
        $customer = $this->resolveCustomer($request, $customerId);

        $validated = $request->validate([
            'loginTypeLevel' => 'required|string|in:1,3,4,5,6,7,8,10',
            'telecom' => 'required|string|in:0,1,2',
            'date' => 'nullable|string',
        ]);

        $agentId = $request->user()->agent->agent_id;

        try {
            $result = $this->predictionService->fetchHealthAge([
                'customer' => $customer,
                'customer_id' => $customer->customer_id,
                'loginTypeLevel' => $validated['loginTypeLevel'],
                'userName' => $customer->name,
                'birthDate' => $customer->birth_date?->format('Ymd'),
                'phoneNo' => $customer->phone,
                'telecom' => $validated['telecom'],
                'type' => '0',
                'date' => $validated['date'] ?? null,
            ]);
        } catch (\RuntimeException $e) {
            $this->logApiCall($agentId, $customer->customer_id, CodefApiLog::TYPE_HEALTH_AGE, CodefApiLog::ACTION_FETCH, CodefApiLog::STATUS_FAILED, 0, $e->getMessage());
            return response()->json([
                'success' => false,
                'code' => 'MISSING_CUSTOMER_INFO',
                'message' => $e->getMessage(),
            ], 422);
        }

        if ($result['two_way'] ?? false) {
            $this->logApiCall($agentId, $customer->customer_id, CodefApiLog::TYPE_HEALTH_AGE, CodefApiLog::ACTION_FETCH, CodefApiLog::STATUS_TWO_WAY);
            return $this->buildResponse($result);
        }

        if ($result['success'] && !empty($result['data'])) {
            $this->logApiCall($agentId, $customer->customer_id, CodefApiLog::TYPE_HEALTH_AGE, CodefApiLog::ACTION_FETCH, CodefApiLog::STATUS_SUCCESS);
            return $this->saveHealthAgeAndRespond($customer->customer_id, $result['data']);
        }

        $this->logApiCall($agentId, $customer->customer_id, CodefApiLog::TYPE_HEALTH_AGE, CodefApiLog::ACTION_FETCH, CodefApiLog::STATUS_FAILED, 0, $result['message'] ?? null);
        return $this->buildResponse($result);
    }

    /**
     * 건강나이 2-Way 확인
     * POST /api/agent/codef/{customerId}/health-age/confirm
     */
    public function confirmHealthAge(Request $request, string $customerId): JsonResponse
    {
        $customer = $this->resolveCustomer($request, $customerId);
        $agentId = $request->user()->agent->agent_id;

        $result = $this->predictionService->confirmHealthAge(
            $request->all(),
            $customer->customer_id
        );

        if ($result['success'] && !empty($result['data'])) {
            $this->logApiCall($agentId, $customer->customer_id, CodefApiLog::TYPE_HEALTH_AGE, CodefApiLog::ACTION_CONFIRM, CodefApiLog::STATUS_SUCCESS);
            return $this->saveHealthAgeAndRespond($customer->customer_id, $result['data']);
        }

        $this->logApiCall($agentId, $customer->customer_id, CodefApiLog::TYPE_HEALTH_AGE, CodefApiLog::ACTION_CONFIRM, CodefApiLog::STATUS_FAILED, 0, $result['message'] ?? null);
        return $this->buildResponse($result);
    }

    // ========================================================================
    // 헬퍼
    // ========================================================================

    /**
     * CODEF API 사용 로그 기록
     */
    private function logApiCall(
        string $agentId,
        string $customerId,
        string $apiType,
        string $apiAction,
        string $status,
        int $resultCount = 0,
        ?string $errorMessage = null
    ): void {
        try {
            CodefApiLog::create([
                'agent_id' => $agentId,
                'customer_id' => $customerId,
                'api_type' => $apiType,
                'api_action' => $apiAction,
                'status' => $status,
                'result_count' => $resultCount,
                'error_message' => $errorMessage ? mb_substr($errorMessage, 0, 500) : null,
                'billing_month' => now()->format('Y-m'),
            ]);
        } catch (\Exception $e) {
            Log::error('CODEF API 로그 기록 실패', [
                'agent_id' => $agentId,
                'customer_id' => $customerId,
                'api_type' => $apiType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 해당 고객이 설계사 소속인지 검증하고 Customer 반환
     */
    private function resolveCustomer(Request $request, string $customerId): Customer
    {
        $agentId = $request->user()->agent->agent_id;

        return Customer::where('agent_id', $agentId)
            ->where('customer_id', $customerId)
            ->firstOrFail();
    }

    /**
     * 진료내역 저장 + 응답
     */
    private function saveMedicalAndRespond(string $customerId, array $data, string $startDate, string $endDate): JsonResponse
    {
        try {
            $count = $this->hiraService->storeMedicalInfo($customerId, $data, $startDate, $endDate);

            return response()->json([
                'success' => true,
                'message' => '진료내역이 저장되었습니다.',
                'data' => ['count' => $count],
            ]);
        } catch (\Exception $e) {
            Log::error('설계사 HIRA 진료내역 저장 실패', [
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
     * 검진결과 저장 + 응답
     */
    private function saveCheckupAndRespond(string $customerId, array $data): JsonResponse
    {
        try {
            $saved = $this->nhisHealthService->storeCheckupResult($customerId, $data);

            if (count($saved) === 0) {
                return response()->json([
                    'success' => true,
                    'code' => 'NO_RECORDS',
                    'message' => '최근 10년 내 건강검진 이력이 없습니다.',
                    'data' => ['count' => 0, 'checkups' => []],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => '건강검진결과가 저장되었습니다.',
                'data' => ['count' => count($saved), 'checkups' => $saved],
            ]);
        } catch (\Exception $e) {
            Log::error('설계사 NHIS 검진결과 저장 실패', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'code' => 'SAVE_ERROR',
                'message' => '검진결과 저장 중 오류가 발생했습니다.',
            ], 500);
        }
    }

    /**
     * 건강나이 저장 + 응답
     */
    private function saveHealthAgeAndRespond(string $customerId, array $data): JsonResponse
    {
        try {
            $row = $this->predictionService->storeHealthAge($customerId, $data);

            return response()->json([
                'success' => true,
                'data' => $row,
                'message' => '건강나이가 저장되었습니다.',
            ]);
        } catch (\Exception $e) {
            Log::error('설계사 건강나이 저장 실패', [
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

    /**
     * CODEF 서비스 응답 -> HTTP 응답 변환 (기존 Credit4uController 패턴)
     */
    private function buildResponse(array $result): JsonResponse
    {
        $statusCode = 200;

        if (!($result['success'] ?? false)) {
            $code = $result['code'] ?? 'ERROR';

            // 2-Way 응답은 200으로 반환 (Frontend에서 모달 처리)
            if ($code === 'CF-03002' || ($result['two_way'] ?? false)) {
                $statusCode = 200;
            } elseif (in_array($code, ['TWO_WAY_EXPIRED', 'INVALID_API_TYPE'])) {
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
