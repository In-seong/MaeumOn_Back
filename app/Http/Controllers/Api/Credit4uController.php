<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Credit4uAccount;
use App\Services\Codef\Credit4uService;
use App\Services\Insurance\InsuranceContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class Credit4uController extends Controller
{
    protected Credit4uService $credit4uService;
    protected InsuranceContractService $contractService;

    public function __construct(
        Credit4uService $credit4uService,
        InsuranceContractService $contractService
    ) {
        $this->credit4uService = $credit4uService;
        $this->contractService = $contractService;
    }

    /**
     * 정보이용 동의 저장
     * POST /api/credit4u/consent
     */
    public function consent(Request $request): JsonResponse
    {
        $customerId = $request->user()->customer->customer_id;

        $validated = $request->validate([
            'consent_template_id' => 'nullable|integer|exists:consent_template,consent_template_id',
        ]);

        // credit4u_account 레코드 생성 또는 업데이트
        $account = Credit4uAccount::updateOrCreate(
            ['customer_id' => $customerId],
            [
                'registration_status' => 'consented',
                'consent_template_id' => $validated['consent_template_id'] ?? null,
                'consent_agreed_at' => now(),
                'is_active' => true,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $account,
            'message' => '정보이용 동의가 저장되었습니다.',
        ]);
    }

    /**
     * 가입여부 확인
     * POST /api/credit4u/check-registration
     */
    public function checkRegistration(Request $request): JsonResponse
    {
        $customerId = $request->user()->customer->customer_id;

        $validated = $request->validate([
            'id' => 'required|string',
            'password' => 'required|string',
            'inquiryType' => 'nullable|string|in:0,4',
        ]);

        $data = array_merge($validated, ['customer_id' => $customerId]);
        $inquiryType = $validated['inquiryType'] ?? '0';

        $result = $this->credit4uService->checkRegistration($data, $inquiryType);

        // 성공 시 credit4u_account 상태 업데이트
        if ($result['success']) {
            $this->updateAccountStatus($customerId, $result, $validated['id']);
        }

        // CF-13302(5회 실패), CF-13303(임시비밀번호) → 비밀번호 변경 필요 상태로 변환
        $code = $result['code'] ?? '';
        if (in_array($code, ['CF-13302', 'CF-13303'])) {
            Credit4uAccount::updateOrCreate(
                ['customer_id' => $customerId],
                [
                    'credit4u_login_id' => $validated['id'],
                    'registration_status' => 'temp_password',
                ]
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'registration_status' => '3',
                    'credit4u_account' => Credit4uAccount::where('customer_id', $customerId)->first(),
                ],
                'message' => $result['message'],
            ]);
        }

        return $this->buildResponse($result);
    }

    /**
     * 회원가입 신청
     * POST /api/credit4u/register
     */
    public function register(Request $request): JsonResponse
    {
        $customerId = $request->user()->customer->customer_id;

        $validated = $request->validate([
            'userName' => 'required|string',
            'identity' => 'required|string',
            'birthDate' => 'nullable|string',
            'phoneNo' => 'required|string',
            'telecom' => 'required|string',
            'id' => 'nullable|string',
            'password' => 'nullable|string',
            'email' => 'nullable|email',
            'type' => 'nullable|string|in:0,1',
        ]);

        $data = array_merge($validated, ['customer_id' => $customerId]);
        $type = $validated['type'] ?? '0';

        $result = $this->credit4uService->register($data, $type);

        // 가입 성공 시 credit4u_account 업데이트
        if ($result['success']) {
            $responseData = $result['data'] ?? [];
            $loginId = $responseData['resLoginId'] ?? ($validated['id'] ?? null);

            Credit4uAccount::updateOrCreate(
                ['customer_id' => $customerId],
                [
                    'credit4u_login_id' => $loginId,
                    'registration_status' => 'registered',
                ]
            );
        }

        return $this->buildResponse($result);
    }

    /**
     * 계약정보 조회 (CODEF → DB 저장)
     * POST /api/credit4u/fetch-contracts
     */
    public function fetchContracts(Request $request): JsonResponse
    {
        $customerId = $request->user()->customer->customer_id;

        $validated = $request->validate([
            'id' => 'required|string',
            'password' => 'required|string',
            // status=2인 경우 추가 파라미터
            'userName' => 'nullable|string',
            'identity' => 'nullable|string',
            'birthDate' => 'nullable|string',
            'phoneNo' => 'nullable|string',
            'telecom' => 'nullable|string',
        ]);

        $loginData = [
            'id' => $validated['id'],
            'password' => $validated['password'],
            'customer_id' => $customerId,
        ];

        // status=2 추가 파라미터
        $extraParams = null;
        if (!empty($validated['userName']) || !empty($validated['identity'])) {
            $extraParams = array_filter([
                'userName' => $validated['userName'] ?? null,
                'identity' => $validated['identity'] ?? null,
                'birthDate' => $validated['birthDate'] ?? null,
                'phoneNo' => $validated['phoneNo'] ?? null,
                'telecom' => $validated['telecom'] ?? null,
            ]);
        }

        $result = $this->credit4uService->getContractInfo($loginData, $extraParams);

        // 성공 시 DB에 저장
        if ($result['success'] && !empty($result['data'])) {
            try {
                $this->contractService->syncContracts($customerId, $result['data']);

                // credit4u_account 동기화 시각 업데이트
                Credit4uAccount::where('customer_id', $customerId)->update([
                    'credit4u_login_id' => $validated['id'],
                    'registration_status' => 'registered',
                    'last_synced_at' => now(),
                ]);

                // DB에서 저장된 데이터를 반환
                $contracts = $this->contractService->getCustomerContracts($customerId);

                return response()->json([
                    'success' => true,
                    'data' => $contracts,
                    'message' => '보험 계약정보가 동기화되었습니다.',
                ]);

            } catch (\Exception $e) {
                Log::error('계약정보 DB 저장 실패', [
                    'customer_id' => $customerId,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => '계약정보 저장 중 오류가 발생했습니다.',
                ], 500);
            }
        }

        return $this->buildResponse($result);
    }

    /**
     * 아이디 찾기
     * POST /api/credit4u/find-id
     */
    public function findId(Request $request): JsonResponse
    {
        $customerId = $request->user()->customer->customer_id;

        $validated = $request->validate([
            'userName' => 'required|string',
            'identity' => 'required|string',
            'birthDate' => 'nullable|string',
            'phoneNo' => 'required|string',
            'telecom' => 'required|string',
        ]);

        $data = array_merge($validated, ['customer_id' => $customerId]);

        $result = $this->credit4uService->findId($data);

        return $this->buildResponse($result);
    }

    /**
     * 비밀번호 변경
     * POST /api/credit4u/change-password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $customerId = $request->user()->customer->customer_id;

        $validated = $request->validate([
            'id' => 'required|string',
            'password' => 'nullable|string',
            'userName' => 'required|string',
            'identity' => 'required|string',
            'birthDate' => 'nullable|string',
            'phoneNo' => 'required|string',
            'telecom' => 'required|string',
            'type' => 'nullable|string|in:0,1',
        ]);

        $data = array_merge($validated, ['customer_id' => $customerId]);
        $type = $validated['type'] ?? '0';

        $result = $this->credit4uService->changePassword($data, $type);

        return $this->buildResponse($result);
    }

    /**
     * 2-Way 추가인증 확인 (범용)
     * POST /api/credit4u/2way-confirm
     */
    public function twoWayConfirm(Request $request): JsonResponse
    {
        $customerId = $request->user()->customer->customer_id;

        $validated = $request->validate([
            'apiType' => 'required|string',
            // 추가인증 입력값 (단계별로 다름)
            'secureNo' => 'nullable|string',
            'smsAuthNo' => 'nullable|string',
            'simpleAuth' => 'nullable|string',
            'password1' => 'nullable|string',  // 임시비밀번호
            'id' => 'nullable|string',          // 회원가입 아이디
            'password' => 'nullable|string',    // 회원가입/비밀번호변경
            'email' => 'nullable|string',       // 회원가입 이메일
            'emailAuthNo' => 'nullable|string', // 이메일 인증번호
        ]);

        $apiType = $validated['apiType'];

        // apiType 이외의 입력값을 twoWayInput으로 구성
        $twoWayInput = array_filter(
            collect($validated)->except('apiType')->toArray(),
            fn ($value) => $value !== null
        );

        $result = $this->credit4uService->confirm2Way($apiType, $twoWayInput, $customerId);

        // 계약정보 조회 2-Way가 성공한 경우 DB 저장
        if ($result['success'] && $apiType === Credit4uService::API_TYPE_CONTRACT && !empty($result['data'])) {
            try {
                $this->contractService->syncContracts($customerId, $result['data']);

                Credit4uAccount::where('customer_id', $customerId)->update([
                    'last_synced_at' => now(),
                ]);

                $contracts = $this->contractService->getCustomerContracts($customerId);

                return response()->json([
                    'success' => true,
                    'data' => $contracts,
                    'message' => '보험 계약정보가 동기화되었습니다.',
                ]);

            } catch (\Exception $e) {
                Log::error('2-Way 후 계약정보 DB 저장 실패', [
                    'customer_id' => $customerId,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => '계약정보 저장 중 오류가 발생했습니다.',
                ], 500);
            }
        }

        // 회원가입 2-Way 성공 시 credit4u_account 업데이트
        if ($result['success'] && $apiType === Credit4uService::API_TYPE_REGISTER) {
            $responseData = $result['data'] ?? [];
            $loginId = $responseData['resLoginId'] ?? null;

            if ($loginId) {
                Credit4uAccount::where('customer_id', $customerId)->update([
                    'credit4u_login_id' => $loginId,
                    'registration_status' => 'registered',
                ]);
            }
        }

        return $this->buildResponse($result);
    }

    /**
     * credit4u_account 상태 업데이트 (가입여부 확인 후)
     */
    private function updateAccountStatus(string $customerId, array $result, string $loginId): void
    {
        $responseData = $result['data'] ?? [];
        $status = $responseData['resRegistrationStatus'] ?? null;

        $registrationStatus = match ($status) {
            '1' => 'registered',
            '2' => 'needs_verify',
            '3' => 'temp_password',
            default => null,
        };

        if ($registrationStatus) {
            Credit4uAccount::updateOrCreate(
                ['customer_id' => $customerId],
                [
                    'credit4u_login_id' => $loginId,
                    'registration_status' => $registrationStatus,
                ]
            );
        }
    }

    /**
     * CODEF 서비스 응답 → HTTP 응답 변환
     */
    private function buildResponse(array $result): JsonResponse
    {
        $statusCode = 200;

        if (!$result['success']) {
            $code = $result['code'] ?? 'ERROR';

            // 2-Way 응답은 200으로 반환 (Frontend에서 모달 처리)
            if ($code === 'CF-03002' || ($result['two_way'] ?? false)) {
                $statusCode = 200;
            } elseif (in_array($code, ['TWO_WAY_EXPIRED', 'INVALID_API_TYPE'])) {
                $statusCode = 400;
            } elseif ($code === 'TIMEOUT') {
                $statusCode = 504;
            } elseif (str_starts_with($code, 'HTTP_')) {
                $statusCode = 502;
            } else {
                $statusCode = 422;
            }
        }

        $response = [
            'success' => $result['success'],
            'code' => $result['code'] ?? null,
            'message' => $result['message'] ?? '',
        ];

        // 데이터가 있으면 포함
        if (isset($result['data'])) {
            $response['data'] = $result['data'];
        }

        // 2-Way 관련 정보
        if (isset($result['two_way'])) {
            $response['two_way'] = $result['two_way'];
        }
        if (isset($result['extraInfo'])) {
            $response['extraInfo'] = $result['extraInfo'];
        }

        return response()->json($response, $statusCode);
    }
}
