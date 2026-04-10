<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\HealthPrediction;
use App\Services\Codef\NhisPredictionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 건강나이 + 예측 5종 배치 조회
 *
 * 1회 간편인증으로 6개 API를 순차 호출 (실행계획 §3.3)
 *
 * 플로우:
 * 1. request: 건강나이 API 1-Way 호출 → 2-Way 응답 반환
 * 2. confirm: 건강나이 confirm + 예측 5종 순차 1-Way 호출
 *    → 동일 `id`(customer_id) 로 세션 재사용 시도
 *    → 실패한 API는 failed 배열에 담아 FE에서 개별 재시도 가능
 */
class HealthPredictionBatchController extends Controller
{
    /** 배치 대상 예측 5종 */
    private const PREDICTION_TYPES = [
        HealthPrediction::TYPE_CARDIO,
        HealthPrediction::TYPE_STROKE,
        HealthPrediction::TYPE_DIABETES,
        HealthPrediction::TYPE_KIDNEY,
        HealthPrediction::TYPE_MI,
    ];

    protected NhisPredictionService $predictionService;

    public function __construct(NhisPredictionService $predictionService)
    {
        $this->predictionService = $predictionService;
    }

    /**
     * 배치 요청 (1-Way): 건강나이 API 호출
     * POST /api/health/predictions/batch/request
     */
    public function request(Request $request): JsonResponse
    {
        $customer = $request->user()->customer;

        if (!$customer->healthExternalAccount?->checkup_consent_at) {
            return response()->json([
                'success' => false,
                'code' => 'NO_CONSENT',
                'message' => '건강 정보 조회 동의가 필요합니다.',
            ], 403);
        }

        $validated = $this->validateAuthInput($request);

        // 건강iN 연동 여부 — 미연동이면 type='0' (신규사용자) 강제
        // 항상 type="0"(신규사용자) 사용.
        // type="1"(기존사용자)은 건강iN userId/userPassword가 필요하므로 2-Way 트리거됨.
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
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'code' => 'MISSING_CUSTOMER_INFO',
                'message' => $e->getMessage(),
            ], 422);
        }

        // 2-Way 필요 → FE 모달 표시
        if ($result['two_way'] ?? false) {
            return $this->buildResponse($result);
        }

        // 드물게 1-Way로 바로 성공한 경우 → health-age 저장 + 5종 순차 호출
        if (($result['success'] ?? false) && !empty($result['data'])) {
            return $this->processBatch($customer, $result['data'], $validated, $linkType);
        }

        return $this->buildResponse($result);
    }

    /**
     * 배치 2-Way 확인 (카카오톡 인증 완료 후)
     * POST /api/health/predictions/batch/confirm
     *
     * body: { simpleAuth: '1', + authForm 재전송 (userName/birthDate/phoneNo/telecom/loginTypeLevel) }
     */
    public function confirm(Request $request): JsonResponse
    {
        $customer = $request->user()->customer;
        $validated = $this->validateAuthInput($request);

        // 1단계: 건강나이 2-Way 확인
        $twoWayInput = ['simpleAuth' => $request->input('simpleAuth', '1')];
        $result = $this->predictionService->confirmHealthAge($twoWayInput, $customer->customer_id);

        if (!($result['success'] ?? false) || empty($result['data'])) {
            // 2-Way가 또 필요한 경우 (추가 단계) 또는 실패
            return $this->buildResponse($result);
        }

        // 2단계: 건강나이 저장 + 예측 5종 순차 호출
        // 항상 type="0"(신규사용자) 사용.
        // type="1"(기존사용자)은 건강iN userId/userPassword가 필요하므로 2-Way 트리거됨.
        $linkType = '0';
        return $this->processBatch($customer, $result['data'], $validated, $linkType);
    }

    /**
     * 건강나이 결과 저장 + 예측 5종 순차 호출
     * (세션 재사용을 위해 모두 동일 customer_id를 id로 전달)
     */
    private function processBatch(Customer $customer, array $healthAgeData, array $validated, string $linkType): JsonResponse
    {
        // 6종 순차 호출은 시간이 걸리므로 PHP 실행 시간 확보
        set_time_limit(300);
        $succeeded = [];
        $failed = [];

        // 1) 건강나이 저장
        try {
            $healthAgeRow = $this->predictionService->storeHealthAge($customer->customer_id, $healthAgeData);
            $succeeded[] = [
                'type' => HealthPrediction::TYPE_HEALTH_AGE,
                'data' => $healthAgeRow,
            ];
        } catch (\Exception $e) {
            Log::error('건강나이 저장 실패', [
                'customer_id' => $customer->customer_id,
                'error' => $e->getMessage(),
            ]);
            $failed[] = [
                'type' => HealthPrediction::TYPE_HEALTH_AGE,
                'reason' => 'save_error',
                'message' => '저장 중 오류가 발생했습니다.',
            ];
        }

        // 2) 예측 5종 순차 호출
        foreach (self::PREDICTION_TYPES as $type) {
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
                ]);

                // 세션 재사용 성공 → 저장
                if (($result['success'] ?? false) && !empty($result['data'])) {
                    $row = $this->predictionService->storePrediction($customer->customer_id, $type, $result['data']);
                    $succeeded[] = ['type' => $type, 'data' => $row];
                    continue;
                }

                // 세션 재사용 실패 → 2-Way 요구
                if ($result['two_way'] ?? false) {
                    $failed[] = [
                        'type' => $type,
                        'reason' => 'needs_auth',
                        'message' => '추가 인증이 필요합니다.',
                    ];
                    // 재인증 상태 컨텍스트는 서비스에서 이미 캐싱됨 (별도 API_TYPE 키)
                    continue;
                }

                // 기타 에러
                $failed[] = [
                    'type' => $type,
                    'reason' => $result['code'] ?? 'error',
                    'message' => $result['message'] ?? '조회 실패',
                ];
            } catch (\RuntimeException $e) {
                $failed[] = [
                    'type' => $type,
                    'reason' => 'missing_info',
                    'message' => $e->getMessage(),
                ];
            } catch (\Exception $e) {
                Log::error("예측 {$type} 호출 실패", [
                    'customer_id' => $customer->customer_id,
                    'error' => $e->getMessage(),
                ]);
                $failed[] = [
                    'type' => $type,
                    'reason' => 'exception',
                    'message' => '요청 중 오류가 발생했습니다.',
                ];
            }
        }

        Log::info('건강 예측 배치 완료', [
            'customer_id' => $customer->customer_id,
            'succeeded_count' => count($succeeded),
            'failed_count' => count($failed),
            'failed_types' => array_column($failed, 'type'),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'succeeded' => $succeeded,
                'failed' => $failed,
                'succeeded_count' => count($succeeded),
                'failed_count' => count($failed),
            ],
            'message' => count($failed) === 0
                ? '6종 건강 예측이 모두 업데이트되었습니다.'
                : count($succeeded) . '종 업데이트 성공, ' . count($failed) . '종 실패',
        ]);
    }

    /**
     * 공통 인증 입력 검증
     */
    private function validateAuthInput(Request $request): array
    {
        return $request->validate([
            'loginTypeLevel' => 'required|string|in:1,3,4,5,6,7,8,10',
            'userName' => 'required|string|max:50',
            'birthDate' => 'required|string|regex:/^\d{8}$/',
            'phoneNo' => 'required|string',
            'telecom' => 'required|string|in:0,1,2',
        ]);
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
            } elseif ($code === 'TWO_WAY_EXPIRED') {
                $statusCode = 400;
            } elseif ($code === 'TIMEOUT') {
                $statusCode = 504;
            } else {
                $statusCode = 422;
            }
        }

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
