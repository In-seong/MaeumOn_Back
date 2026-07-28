<?php

namespace App\Services\Codef;

use App\Models\Customer;
use App\Models\HealthExternalAccount;
use App\Models\HealthPrediction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * NHIS 건강나이 + 질환예측 5종 서비스
 *
 * 공통 사항:
 * - organization: "0002"
 * - type 파라미터: "0"=신규사용자, "1"=기존사용자 (건강iN 연동)
 * - 최초 1회는 type="0", 이후 type="1"
 * - userId/userPassword: type="0" 최초 연동 시 필수
 *
 * 5종 예측 API는 입력/출력 스키마가 동일함
 */
class NhisPredictionService
{
    protected CodefApiService $apiService;
    protected CodefRsaEncryptor $encryptor;
    protected string $organization;

    /** Endpoints */
    private const EP_HEALTH_AGE = '/v1/kr/public/pp/hi-nhis-list/review-health-age';
    private const EP_CARDIO = '/v1/kr/public/pp/hi-nhis-list/cardio-cerebrovascular';
    private const EP_STROKE = '/v1/kr/public/pp/hi-nhis-list/stroke';
    private const EP_DIABETES = '/v1/kr/public/pp/hi-nhis-list/diabetes';
    private const EP_KIDNEY = '/v1/kr/public/pp/hi-nhis-list/kidney-disease';
    private const EP_MI = '/v1/kr/public/pp/hi-nhis-list/myocardial-infarction';

    /** API 유형 식별자 (2-Way 캐시 키) */
    public const API_TYPE_HEALTH_AGE = 'nhis-health-age';
    public const API_TYPE_CARDIO = 'nhis-pred-cardio';
    public const API_TYPE_STROKE = 'nhis-pred-stroke';
    public const API_TYPE_DIABETES = 'nhis-pred-diabetes';
    public const API_TYPE_KIDNEY = 'nhis-pred-kidney';
    public const API_TYPE_MI = 'nhis-pred-mi';

    /** 예측 type → endpoint 매핑 */
    private const ENDPOINT_MAP = [
        HealthPrediction::TYPE_CARDIO => self::EP_CARDIO,
        HealthPrediction::TYPE_STROKE => self::EP_STROKE,
        HealthPrediction::TYPE_DIABETES => self::EP_DIABETES,
        HealthPrediction::TYPE_KIDNEY => self::EP_KIDNEY,
        HealthPrediction::TYPE_MI => self::EP_MI,
    ];

    /** 예측 type → API_TYPE 매핑 */
    private const API_TYPE_MAP = [
        HealthPrediction::TYPE_CARDIO => self::API_TYPE_CARDIO,
        HealthPrediction::TYPE_STROKE => self::API_TYPE_STROKE,
        HealthPrediction::TYPE_DIABETES => self::API_TYPE_DIABETES,
        HealthPrediction::TYPE_KIDNEY => self::API_TYPE_KIDNEY,
        HealthPrediction::TYPE_MI => self::API_TYPE_MI,
    ];

    public function __construct(CodefApiService $apiService, CodefRsaEncryptor $encryptor)
    {
        $this->apiService = $apiService;
        $this->encryptor = $encryptor;
        $this->organization = '0002'; // NHIS
    }

    // ========================================================================
    // 1. 건강나이
    // ========================================================================

    /**
     * 건강나이 조회 (1-Way)
     *
     * @param array $data [customer, customer_id, loginTypeLevel, type, date?, userId?, userPassword?]
     */
    public function fetchHealthAge(array $data): array
    {
        $params = $this->buildPredictionParams($data);

        $result = $this->apiService->callApi(
            self::EP_HEALTH_AGE,
            $params,
            $data['customer_id'] ?? null,
            self::API_TYPE_HEALTH_AGE
        );

        if ($this->isTwoWay($result) && isset($data['customer_id'])) {
            $this->apiService->storeTwoWayContext(
                $data['customer_id'],
                self::API_TYPE_HEALTH_AGE,
                self::EP_HEALTH_AGE,
                $params
            );
        }

        return $result;
    }

    public function confirmHealthAge(array $twoWayInput, string $customerId): array
    {
        return $this->confirmGeneric(self::EP_HEALTH_AGE, self::API_TYPE_HEALTH_AGE, $twoWayInput, $customerId);
    }

    /**
     * 건강나이 결과를 health_prediction 테이블에 저장
     */
    public function storeHealthAge(string $customerId, array $codefResult): HealthPrediction
    {
        return DB::transaction(function () use ($customerId, $codefResult) {
            $row = HealthPrediction::updateOrCreate(
                [
                    'customer_id' => $customerId,
                    'prediction_type' => HealthPrediction::TYPE_HEALTH_AGE,
                    'checkup_date' => $this->normalizeDate($codefResult['resCheckupDate'] ?? null),
                ],
                [
                    'health_age' => $codefResult['resAge'] ?? null,
                    'chronological_age' => $codefResult['resChronologicalAge'] ?? null,
                    'change_after_text' => $codefResult['resChangeAfter'] ?? ($codefResult['resNote1'] ?? null),
                    'detail_list_json' => isset($codefResult['resDetailList'])
                        ? json_encode($codefResult['resDetailList'], JSON_UNESCAPED_UNICODE)
                        : null,
                    'predicted_at' => now(),
                    'codef_raw_data' => json_encode($codefResult, JSON_UNESCAPED_UNICODE),
                ]
            );

            HealthExternalAccount::updateOrCreate(
                ['customer_id' => $customerId],
                ['last_health_age_sync_at' => now()]
            );

            return $row;
        });
    }

    // ========================================================================
    // 2. 예측 5종 (cardio/stroke/diabetes/kidney/mi)
    // ========================================================================

    /**
     * 예측 API 호출 (1-Way) - 통합 메서드
     *
     * @param string $type     prediction type (cardio/stroke/diabetes/kidney/mi)
     * @param array  $data     [customer, customer_id, loginTypeLevel, type(=apiType), date?, userId?, userPassword?]
     */
    public function fetchPrediction(string $type, array $data): array
    {
        if (!isset(self::ENDPOINT_MAP[$type])) {
            return [
                'success' => false,
                'code' => 'INVALID_TYPE',
                'message' => '유효하지 않은 예측 유형입니다.',
            ];
        }

        $endpoint = self::ENDPOINT_MAP[$type];
        $apiType = self::API_TYPE_MAP[$type];

        $params = $this->buildPredictionParams($data);

        $result = $this->apiService->callApi(
            $endpoint,
            $params,
            $data['customer_id'] ?? null,
            $apiType
        );

        if ($this->isTwoWay($result) && isset($data['customer_id'])) {
            $this->apiService->storeTwoWayContext(
                $data['customer_id'],
                $apiType,
                $endpoint,
                $params
            );
        }

        return $result;
    }

    public function confirmPrediction(string $type, array $twoWayInput, string $customerId): array
    {
        if (!isset(self::ENDPOINT_MAP[$type])) {
            return [
                'success' => false,
                'code' => 'INVALID_TYPE',
                'message' => '유효하지 않은 예측 유형입니다.',
            ];
        }

        return $this->confirmGeneric(
            self::ENDPOINT_MAP[$type],
            self::API_TYPE_MAP[$type],
            $twoWayInput,
            $customerId
        );
    }

    /**
     * 예측 결과 저장 (5종 공통)
     */
    public function storePrediction(string $customerId, string $type, array $codefResult): HealthPrediction
    {
        return DB::transaction(function () use ($customerId, $type, $codefResult) {
            $row = HealthPrediction::updateOrCreate(
                [
                    'customer_id' => $customerId,
                    'prediction_type' => $type,
                    'checkup_date' => $this->normalizeDate($codefResult['resCheckupDate'] ?? null),
                ],
                [
                    'risk_grade' => $codefResult['resRiskGrade'] ?? null,
                    'risk_ratio' => $codefResult['resRatio'] ?? null,
                    'average_age' => $codefResult['resAverageAge'] ?? null,
                    'average_ratio' => $codefResult['resAverageRatio'] ?? null,
                    'detail_list_json' => isset($codefResult['resDetailList'])
                        ? json_encode($codefResult['resDetailList'], JSON_UNESCAPED_UNICODE)
                        : null,
                    'sub_detail_list_json' => isset($codefResult['resSubDetailList'])
                        ? json_encode($codefResult['resSubDetailList'], JSON_UNESCAPED_UNICODE)
                        : null,
                    'compare_list_json' => isset($codefResult['resCompareList'])
                        ? json_encode($codefResult['resCompareList'], JSON_UNESCAPED_UNICODE)
                        : null,
                    'predicted_at' => now(),
                    'codef_raw_data' => json_encode($codefResult, JSON_UNESCAPED_UNICODE),
                ]
            );

            HealthExternalAccount::updateOrCreate(
                ['customer_id' => $customerId],
                ['last_prediction_sync_at' => now()]
            );

            return $row;
        });
    }

    // ========================================================================
    // 헬퍼
    // ========================================================================

    /**
     * 예측 API 공통 파라미터 빌드 (인증 + type/date/userId/userPassword)
     *
     * ⚠️ NHIS 간편인증(loginType="5")에서 `identity` = 생년월일 YYYYMMDD
     *
     * @param array $data [customer?, loginTypeLevel, userName, birthDate, phoneNo, telecom, type, date?, userId?, userPassword?]
     */
    protected function buildPredictionParams(array $data): array
    {
        /** @var Customer|null $customer */
        $customer = $data['customer'] ?? null;
        $linkType = $data['type'] ?? '0'; // 기본: 신규사용자 (건강iN 미연동)

        $userName = $data['userName'] ?? $customer?->name;
        $birthDate = $data['birthDate'] ?? $customer?->birth_date?->format('Ymd');
        $phoneNo = $data['phoneNo'] ?? $customer?->phone;
        $telecom = $data['telecom'] ?? $customer?->telecom;

        if ($userName === null || $userName === '' || $birthDate === null || $birthDate === '' || $phoneNo === null || $phoneNo === '' || $telecom === null || $telecom === '') {
            throw new \RuntimeException('인증정보(이름/생년월일/전화번호/통신사)를 입력해주세요.');
        }

        $birthDateDigits = preg_replace('/\D/', '', (string) $birthDate);
        if (strlen($birthDateDigits) !== 8) {
            throw new \RuntimeException('생년월일은 YYYYMMDD 8자리로 입력해주세요.');
        }

        $params = [
            'organization' => $this->organization,
            'loginType' => '5',
            'loginTypeLevel' => (string) $data['loginTypeLevel'],
            'userName' => $userName,
            // ★ NHIS 간편인증: identity = 생년월일 YYYYMMDD
            'identity' => $birthDateDigits,
            'phoneNo' => preg_replace('/\D/', '', $phoneNo),
            'telecom' => (string) $telecom,
            'id' => $customer?->customer_id ?? ($data['customer_id'] ?? null),
            'type' => (string) $linkType,
        ];

        // 검진일자 (선택)
        if (!empty($data['date'])) {
            $params['date'] = preg_replace('/\D/', '', $data['date']);
        }

        // 건강iN 신규 연동 (type="0") → userId/userPassword 필수
        if ($linkType === '0') {
            if (!empty($data['userId'])) {
                $params['userId'] = $data['userId'];
            }
            if (!empty($data['userPassword'])) {
                $params['userPassword'] = $this->encryptor->encrypt($data['userPassword']);
            }
        }

        return $params;
    }

    /**
     * 2-Way 확인 통합 메서드
     */
    protected function confirmGeneric(string $endpoint, string $apiType, array $twoWayInput, string $customerId): array
    {
        $context = $this->apiService->getTwoWayContext($customerId, $apiType);
        if (!$context) {
            return [
                'success' => false,
                'code' => 'TWO_WAY_EXPIRED',
                'message' => '추가인증 세션이 만료되었습니다.',
            ];
        }

        return $this->apiService->callApi2Way(
            $context['endpoint'] ?? $endpoint,
            $context['originalParams'] ?? [],
            $twoWayInput,
            $customerId,
            $apiType
        );
    }

    protected function normalizeDate(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $value);
        if (strlen($digits) !== 8) {
            return null;
        }
        return substr($digits, 0, 4) . '-' . substr($digits, 4, 2) . '-' . substr($digits, 6, 2);
    }

    private function isTwoWay(array $result): bool
    {
        return ($result['two_way'] ?? false) === true;
    }
}
