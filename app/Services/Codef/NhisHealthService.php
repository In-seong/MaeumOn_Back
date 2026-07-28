<?php

namespace App\Services\Codef;

use App\Models\Customer;
use App\Models\HealthCheckup;
use App\Models\HealthExternalAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * NHIS (국민건강보험공단) 건강 API 서비스
 *
 * Phase 1: 건강검진결과 (resPreviewList → health_checkup 저장)
 * Phase 2: 검진대상 (캐시 저장)
 *
 * 인증 방식: loginType="5" (간편인증) 우선
 * organization: "0002"
 */
class NhisHealthService
{
    protected CodefApiService $apiService;
    protected CodefRsaEncryptor $encryptor;
    protected string $organization;

    /** Endpoints */
    private const EP_CHECKUP = '/v1/kr/public/pp/nhis-health-checkup/result';
    private const EP_EXAMINATION = '/v1/kr/public/pp/nhis-list/examination';

    /** API 유형 식별자 (2-Way 캐시 키) */
    public const API_TYPE_CHECKUP = 'nhis-checkup';
    public const API_TYPE_EXAMINATION = 'nhis-examination';

    /** 검진결과 조회 옵션 */
    private const INQUIRY_TYPE_JSON = '4'; // JSON 문진 + 검진결과

    public function __construct(CodefApiService $apiService, CodefRsaEncryptor $encryptor)
    {
        $this->apiService = $apiService;
        $this->encryptor = $encryptor;
        $this->organization = '0002'; // NHIS 고정
    }

    // ========================================================================
    // 1. 건강검진결과 (Phase 1)
    // ========================================================================

    /**
     * 건강검진결과 조회 (1-Way)
     *
     * @param array $data [customer, loginTypeLevel, customer_id, userName, identity, phoneNo, telecom, birthDate?, searchStartYear?, searchEndYear?, type?]
     */
    public function fetchCheckupResult(array $data): array
    {
        $params = $this->buildAuthParams($data);

        // 건강검진 전용 파라미터
        $params['inquiryType'] = self::INQUIRY_TYPE_JSON;
        $params['type'] = $data['type'] ?? '0'; // 0=전체

        // inquiryType > 0 일 때 searchStartYear/searchEndYear 필수 (PDF 가이드)
        // 기본: 최근 10년 범위 (NHIS 검진결과 제공 기간)
        $currentYear = (int) date('Y');
        $defaultEnd = (string) $currentYear;
        $defaultStart = (string) ($currentYear - 9);  // 올해 포함 10년

        $params['searchEndYear'] = $this->normalizeYear($data['searchEndYear'] ?? null) ?? $defaultEnd;
        $params['searchStartYear'] = $this->normalizeYear($data['searchStartYear'] ?? null) ?? $defaultStart;

        $result = $this->apiService->callApi(
            self::EP_CHECKUP,
            $params,
            $data['customer_id'] ?? null,
            self::API_TYPE_CHECKUP
        );

        if ($this->isTwoWay($result) && isset($data['customer_id'])) {
            $this->apiService->storeTwoWayContext(
                $data['customer_id'],
                self::API_TYPE_CHECKUP,
                self::EP_CHECKUP,
                $params
            );
        }

        return $result;
    }

    /**
     * 건강검진결과 2-Way 확인
     */
    public function confirmCheckupResult(array $twoWayInput, string $customerId): array
    {
        $context = $this->apiService->getTwoWayContext($customerId, self::API_TYPE_CHECKUP);
        if (!$context) {
            return [
                'success' => false,
                'code' => 'TWO_WAY_EXPIRED',
                'message' => '추가인증 세션이 만료되었습니다.',
            ];
        }

        return $this->apiService->callApi2Way(
            $context['endpoint'] ?? self::EP_CHECKUP,
            $context['originalParams'] ?? [],
            $twoWayInput,
            $customerId,
            self::API_TYPE_CHECKUP
        );
    }

    // ========================================================================
    // 2. 검진대상 (Phase 2)
    // ========================================================================

    /**
     * 검진대상 조회 (1-Way)
     *
     * @param array $data [customer, loginTypeLevel, customer_id, userName, identity, phoneNo, telecom, birthDate?]
     */
    public function fetchExamination(array $data): array
    {
        $params = $this->buildAuthParams($data);

        $result = $this->apiService->callApi(
            self::EP_EXAMINATION,
            $params,
            $data['customer_id'] ?? null,
            self::API_TYPE_EXAMINATION
        );

        if ($this->isTwoWay($result) && isset($data['customer_id'])) {
            $this->apiService->storeTwoWayContext(
                $data['customer_id'],
                self::API_TYPE_EXAMINATION,
                self::EP_EXAMINATION,
                $params
            );
        }

        return $result;
    }

    /**
     * 검진대상 2-Way 확인
     */
    public function confirmExamination(array $twoWayInput, string $customerId): array
    {
        $context = $this->apiService->getTwoWayContext($customerId, self::API_TYPE_EXAMINATION);
        if (!$context) {
            return [
                'success' => false,
                'code' => 'TWO_WAY_EXPIRED',
                'message' => '추가인증 세션이 만료되었습니다.',
            ];
        }

        return $this->apiService->callApi2Way(
            $context['endpoint'] ?? self::EP_EXAMINATION,
            $context['originalParams'] ?? [],
            $twoWayInput,
            $customerId,
            self::API_TYPE_EXAMINATION
        );
    }

    // ========================================================================
    // 3. DB 저장 (검진결과)
    // ========================================================================

    /**
     * CODEF 건강검진결과 응답을 health_checkup 테이블에 저장
     *
     * - resPreviewList[] (한눈에보기) → 회차별 row
     * - resResultList[] (회차별 상세) → opinion, judgement, question_info_json 매핑
     * - 동일 (customer_id, checkup_year, checkup_date)는 updateOrCreate
     *
     * @return HealthCheckup[] 저장된 검진 row 목록
     */
    public function storeCheckupResult(string $customerId, array $codefResult): array
    {
        // CODEF 응답은 단건/다건 양쪽 형태 가능
        // - 단건: { resCheckupTarget, resResultList, resPreviewList, resReferenceList }
        // - 다건: [{ ... }, { ... }]
        // list_is_list 감지 후 평탄화하여 처리
        $isSequential = array_is_list($codefResult) && !empty($codefResult);
        $resultObjects = $isSequential ? $codefResult : [$codefResult];

        // 전체 resPreviewList + resResultList 수집 (여러 객체에 걸쳐 있을 수 있음)
        $previewList = [];
        $resultList = [];
        foreach ($resultObjects as $obj) {
            if (!is_array($obj)) continue;
            $previewList = array_merge($previewList, $obj['resPreviewList'] ?? []);
            $resultList = array_merge($resultList, $obj['resResultList'] ?? []);
        }

        // 진단용 상세 로깅 (실제 값 포함)
        Log::debug('NHIS storeCheckupResult 응답 구조', [
            'customer_id' => $customerId,
            'is_sequential' => $isSequential,
            'top_level_count' => $isSequential ? count($codefResult) : 1,
            'top_level_item_keys' => $isSequential
                ? array_map(
                    fn($obj) => is_array($obj) ? array_keys($obj) : gettype($obj),
                    $codefResult
                )
                : [array_keys($codefResult)],
            'preview_count' => count($previewList),
            'result_count' => count($resultList),
            // 첫 번째 preview 실제 값 (개인정보 아님, 검진 수치)
            'first_preview_values' => !empty($previewList) ? [
                'resCheckupYear' => $previewList[0]['resCheckupYear'] ?? '(missing)',
                'resCheckupDate' => $previewList[0]['resCheckupDate'] ?? '(missing)',
                'resCheckupPlace' => $previewList[0]['resCheckupPlace'] ?? '(missing)',
                'resJudgement' => $previewList[0]['resJudgement'] ?? '(missing)',
                'resBloodPressure' => $previewList[0]['resBloodPressure'] ?? '(missing)',
            ] : null,
            'first_result_values' => !empty($resultList) ? [
                'resType' => $resultList[0]['resType'] ?? '(missing)',
                'resCheckupType' => $resultList[0]['resCheckupType'] ?? '(missing)',
                'resCheckupYear' => $resultList[0]['resCheckupYear'] ?? '(missing)',
                'resCheckupDate' => $resultList[0]['resCheckupDate'] ?? '(missing)',
            ] : null,
        ]);


        // resResultList를 (year, date) 키로 인덱싱하여 빠른 매칭
        $resultIndex = [];
        foreach ($resultList as $r) {
            $year = (string) ($r['resCheckupYear'] ?? '');
            $date = (string) ($r['resCheckupDate'] ?? '');
            $resultIndex[$year . '|' . $date] = $r;
        }

        $saved = [];

        $skipped = [];

        DB::transaction(function () use ($customerId, $previewList, $resultIndex, $codefResult, &$saved, &$skipped) {
            // 재동기화 안전성: 기존 CODEF 동기화 데이터를 삭제 후 재생성
            // (placeholder 날짜로 저장된 row와 새로운 정확한 날짜 row의 중복 방지)
            HealthCheckup::where('customer_id', $customerId)
                ->where('codef_synced', true)
                ->delete();

            foreach ($previewList as $idx => $preview) {
                $year = (string) ($preview['resCheckupYear'] ?? '');
                $rawDate = $preview['resCheckupDate'] ?? null;

                // 연도 없으면 진짜 skip
                if ($year === '' || !preg_match('/^\d{4}$/', $year)) {
                    $skipped[] = [
                        'index' => $idx,
                        'reason' => 'missing_or_invalid_year',
                        'raw_year' => $year,
                        'raw_date' => $rawDate,
                    ];
                    continue;
                }

                // resCheckupDate 정규화
                // 실제 CODEF 응답 분석 결과:
                // - NHIS preview: resCheckupDate가 "MMDD" 4자리로 옴 (연도는 별도 필드)
                // - NHIS result:  resCheckupDate가 "YYYYMMDD" 8자리
                // → 두 케이스를 모두 처리
                $date = $this->normalizePreviewDate($year, $rawDate);

                if ($date === null) {
                    // 완전히 파싱 불가 → 연도 기반 placeholder
                    Log::info('NHIS preview 검진일자 파싱 불가, 연도 기반 placeholder 사용', [
                        'customer_id' => $customerId,
                        'index' => $idx,
                        'year' => $year,
                        'raw_date' => $rawDate,
                    ]);
                    $date = $year . '-01-01';
                }

                // 동일 회차 detail 매칭 (full YYYYMMDD 재조합)
                $fullYyyymmdd = str_replace('-', '', $date);
                $resultKey = $year . '|' . $fullYyyymmdd;
                $detail = $resultIndex[$resultKey] ?? null;

                if (!$detail) {
                    // 연도만 매칭 fallback
                    foreach ($resultIndex as $key => $r) {
                        if (str_starts_with($key, $year . '|')) {
                            $detail = $r;
                            break;
                        }
                    }
                }
                $detail = $detail ?? [];

                $payload = [
                    'checkup_date' => $date,
                    'checkup_year' => $year,
                    'checkup_type' => $detail['resCheckupType'] ?? '본인검진',
                    'hospital_name' => $preview['resCheckupPlace'] ?? null,
                    'organization_name' => $detail['resOrganizationName'] ?? null,
                    'opinion' => $detail['resOpinion'] ?? null,
                    'judgement' => $preview['resJudgement'] ?? null,

                    // 신체계측
                    'height' => $preview['resHeight'] ?? null,
                    'weight' => $preview['resWeight'] ?? null,
                    'waist' => $preview['resWaist'] ?? null,
                    'bmi' => $preview['resBMI'] ?? null,
                    'sight' => $preview['resSight'] ?? null,
                    'hearing' => $preview['resHearing'] ?? null,

                    // 혈압/요/혈색소
                    'blood_pressure' => $preview['resBloodPressure'] ?? null,
                    'urinary_protein' => $preview['resUrinaryProtein'] ?? null,
                    'hemoglobin' => $preview['resHemoglobin'] ?? null,

                    // 혈당/지질
                    'fasting_blood_sugar' => $preview['resFastingBloodSuger'] ?? null,
                    'total_cholesterol' => $preview['resTotalCholesterol'] ?? null,
                    'hdl_cholesterol' => $preview['resHDLCholesterol'] ?? null,
                    'ldl_cholesterol' => $preview['resLDLCholesterol'] ?? null,
                    'triglyceride' => $preview['resTriglyceride'] ?? null,

                    // 신장/간
                    'serum_creatinine' => $preview['resSerumCreatinine'] ?? null,
                    'gfr' => $preview['resGFR'] ?? null,
                    'ast' => $preview['resAST'] ?? null,
                    'alt' => $preview['resALT'] ?? null,
                    'y_gtp' => $preview['resyGPT'] ?? null,

                    // 기타
                    'tb_chest_disease' => $preview['resTBChestDisease'] ?? null,
                    'osteoporosis' => $preview['resOsteoporosis'] ?? null,

                    // 문진 (resQuestionInfoList)
                    'question_info_json' => isset($detail['resQuestionInfoList'])
                        ? json_encode($detail['resQuestionInfoList'], JSON_UNESCAPED_UNICODE)
                        : null,

                    // 동기화 메타
                    'codef_synced' => true,
                    'synced_at' => now(),
                    'codef_raw_data' => json_encode($codefResult, JSON_UNESCAPED_UNICODE),
                ];

                $row = HealthCheckup::updateOrCreate(
                    [
                        'customer_id' => $customerId,
                        'checkup_year' => $year,
                        'checkup_date' => $date,
                    ],
                    $payload
                );

                $saved[] = $row;
            }

            // health_external_account 동기화 시각 갱신
            HealthExternalAccount::updateOrCreate(
                ['customer_id' => $customerId],
                ['last_checkup_sync_at' => now()]
            );
        });

        Log::info('NHIS 검진결과 저장 완료', [
            'customer_id' => $customerId,
            'saved_count' => count($saved),
            'skipped_count' => count($skipped),
            'skipped_detail' => $skipped,
        ]);

        return $saved;
    }

    // ========================================================================
    // 공통 헬퍼
    // ========================================================================

    /**
     * 요청 데이터 + Customer 폴백으로 NHIS 인증 파라미터 빌드
     *
     * ⚠️ 중요: NHIS 간편인증(loginType="5")에서는 `identity` 필드에
     * **생년월일 YYYYMMDD 8자리**를 입력합니다. 주민등록번호 13자리가 아닙니다.
     * (PDF 가이드 "건강검진결과 API" 참조)
     *
     * loginType="5" (간편인증) 기본
     * loginTypeLevel: 1=카카오톡, 3=삼성패스, 4=KB모바일, 5=PASS, 6=네이버, 7=신한, 8=토스, 10=NH
     *
     * @param array $data [customer?, loginTypeLevel, userName, birthDate, phoneNo, telecom]
     */
    protected function buildAuthParams(array $data): array
    {
        /** @var Customer|null $customer */
        $customer = $data['customer'] ?? null;

        // 요청값 우선, customer 폴백
        $userName = $data['userName'] ?? $customer?->name;
        $birthDate = $data['birthDate'] ?? $customer?->birth_date?->format('Ymd');
        $phoneNo = $data['phoneNo'] ?? $customer?->phone;
        $telecom = $data['telecom'] ?? $customer?->telecom;

        if ($userName === null || $userName === '' || $birthDate === null || $birthDate === '' || $phoneNo === null || $phoneNo === '' || $telecom === null || $telecom === '') {
            throw new \RuntimeException('인증정보(이름/생년월일/전화번호/통신사)를 입력해주세요.');
        }

        // 생년월일 정규화 (YYYYMMDD 8자리)
        $birthDateDigits = preg_replace('/\D/', '', (string) $birthDate);
        if (strlen($birthDateDigits) !== 8) {
            throw new \RuntimeException('생년월일은 YYYYMMDD 8자리로 입력해주세요.');
        }

        return [
            'organization' => $this->organization,
            'loginType' => '5',
            'loginTypeLevel' => (string) $data['loginTypeLevel'],
            'userName' => $userName,
            // ★ NHIS 간편인증: identity = 생년월일 YYYYMMDD (주민번호 아님)
            'identity' => $birthDateDigits,
            'phoneNo' => preg_replace('/\D/', '', $phoneNo),
            'telecom' => (string) $telecom,
            'id' => $customer?->customer_id ?? ($data['customer_id'] ?? null),
        ];
    }

    /**
     * NHIS preview의 resCheckupDate를 Y-m-d로 정규화
     *
     * 실제 응답 분석 결과 지원하는 입력 형태:
     * - "MMDD" (4자리) + year → "YYYY-MM-DD" (NHIS preview 특수 포맷)
     * - "YYYYMMDD" (8자리) → "YYYY-MM-DD"
     * - "YYYY-MM-DD" (10자리) → 그대로 (구분자 제거 후 재조합)
     *
     * @return string|null Y-m-d 형식 또는 파싱 불가 시 null
     */
    protected function normalizePreviewDate(string $year, ?string $rawDate): ?string
    {
        if (empty($rawDate)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', (string) $rawDate);
        $len = strlen($digits);

        // 8자리 YYYYMMDD
        if ($len === 8) {
            return substr($digits, 0, 4) . '-' . substr($digits, 4, 2) . '-' . substr($digits, 6, 2);
        }

        // 4자리 MMDD → year와 합치기 (NHIS preview 특수 케이스)
        if ($len === 4) {
            $month = substr($digits, 0, 2);
            $day = substr($digits, 2, 2);
            // 유효 범위 검증
            if ((int) $month >= 1 && (int) $month <= 12 && (int) $day >= 1 && (int) $day <= 31) {
                return $year . '-' . $month . '-' . $day;
            }
        }

        // 6자리 YYMMDD → 20YY로 가정
        if ($len === 6) {
            $yy = substr($digits, 0, 2);
            $month = substr($digits, 2, 2);
            $day = substr($digits, 4, 2);
            if ((int) $month >= 1 && (int) $month <= 12 && (int) $day >= 1 && (int) $day <= 31) {
                return '20' . $yy . '-' . $month . '-' . $day;
            }
        }

        return null;
    }

    /**
     * 연도 정규화: 유효한 4자리 연도이면 반환, 아니면 null
     */
    protected function normalizeYear(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }
        $digits = preg_replace('/\D/', '', (string) $value);
        return strlen($digits) === 4 ? $digits : null;
    }

    /**
     * CODEF 날짜 문자열 정규화 (YYYYMMDD → Y-m-d)
     */
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

    /**
     * 2-Way 응답 여부 확인
     */
    private function isTwoWay(array $result): bool
    {
        return ($result['two_way'] ?? false) === true;
    }
}
