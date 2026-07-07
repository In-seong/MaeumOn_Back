<?php

namespace App\Services\Codef;

use App\Models\Customer;
use App\Models\HealthExternalAccount;
use App\Models\MedicalRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * HIRA (건강보험심사평가원) 내진료정보열람 서비스
 *
 * Endpoint: /v1/kr/public/hw/hira-list/my-medical-information
 * organization: "0020"
 *
 * ⚠️ 주의사항:
 * - 과도한 호출 시 IP 차단 가능 → 24h 쿨다운 필수
 * - 주민번호 검증 없음 → 앱 측 checksum + customer 일치 확인 필수
 * - 민감상병 포함 (type="1") → 동의서에 명시 필요
 */
class HiraMedicalInfoService
{
    protected CodefApiService $apiService;
    protected CodefRsaEncryptor $encryptor;
    protected string $organization;

    /** Endpoint */
    private const EP_MEDICAL_INFO = '/v1/kr/public/hw/hira-list/my-medical-information';

    /** API 유형 식별자 (2-Way 캐시 키) */
    public const API_TYPE_MEDICAL_INFO = 'hira-medical-info';

    /** IP 차단 방지: 24시간 쿨다운 */
    private const SYNC_COOLDOWN_SECONDS = 86400;

    public function __construct(CodefApiService $apiService, CodefRsaEncryptor $encryptor)
    {
        $this->apiService = $apiService;
        $this->encryptor = $encryptor;
        $this->organization = '0020'; // HIRA 고정
    }

    // ========================================================================
    // 1. 내진료정보 조회 (1-Way)
    // ========================================================================

    /**
     * 내진료정보 조회 요청
     *
     * @param array  $data              [customer, loginTypeLevel, customer_id]
     * @param string $startDate         yyyyMMdd
     * @param string $endDate           yyyyMMdd
     * @param bool   $includeSensitive  true=민감상병 포함 (type="1")
     */
    public function fetchMedicalInfo(
        array $data,
        string $startDate,
        string $endDate,
        bool $includeSensitive = true
    ): array {
        // 주민번호 정합성 검증 (앱 측 안전장치)
        $rawIdentity = $data['identity'] ?? ($data['customer']?->resident_number);
        if (!$this->validateIdentity($rawIdentity)) {
            return [
                'success' => false,
                'code' => 'INVALID_IDENTITY',
                'message' => '주민등록번호 형식이 올바르지 않습니다.',
            ];
        }

        $params = $this->buildAuthParams($data);
        $params['startDate'] = preg_replace('/\D/', '', $startDate);
        $params['endDate'] = preg_replace('/\D/', '', $endDate);
        $params['type'] = $includeSensitive ? '1' : '0';

        $result = $this->apiService->callApi(
            self::EP_MEDICAL_INFO,
            $params,
            $data['customer_id'] ?? null,
            self::API_TYPE_MEDICAL_INFO
        );

        if ($this->isTwoWay($result) && isset($data['customer_id'])) {
            $this->apiService->storeTwoWayContext(
                $data['customer_id'],
                self::API_TYPE_MEDICAL_INFO,
                self::EP_MEDICAL_INFO,
                $params
            );
        }

        return $result;
    }

    /**
     * 내진료정보 2-Way 확인
     */
    public function confirmMedicalInfo(array $twoWayInput, string $customerId): array
    {
        $context = $this->apiService->getTwoWayContext($customerId, self::API_TYPE_MEDICAL_INFO);
        if (!$context) {
            return [
                'success' => false,
                'code' => 'TWO_WAY_EXPIRED',
                'message' => '추가인증 세션이 만료되었습니다.',
            ];
        }

        return $this->apiService->callApi2Way(
            $context['endpoint'] ?? self::EP_MEDICAL_INFO,
            $context['originalParams'] ?? [],
            $twoWayInput,
            $customerId,
            self::API_TYPE_MEDICAL_INFO
        );
    }

    // ========================================================================
    // 2. DB 저장
    // ========================================================================

    /**
     * CODEF 응답을 medical_record 테이블에 저장
     *
     * 정책:
     * - source='codef_hira' & 동기화 구간 내 row를 delete 후 insert (재동기화 안전)
     * - resBasicTreatList[]를 메인 row로 매핑
     * - resDetailTreatList / resPrescribeDrugList는 동일 진료일+병원 기준으로 그룹핑하여 JSON 컬럼에 저장
     * - health_external_account에 동기화 메타 갱신
     *
     * @return int 저장된 row 수
     */
    public function storeMedicalInfo(
        string $customerId,
        array $codefResult,
        string $startDate,
        string $endDate
    ): int {
        $basicList = $codefResult['resBasicTreatList'] ?? [];
        $detailList = $codefResult['resDetailTreatList'] ?? [];
        $drugList = $codefResult['resPrescribeDrugList'] ?? [];

        // 세부진료/처방약을 (date|hospital) 키로 그룹핑
        $detailGroup = $this->groupByTreatKey($detailList);
        $drugGroup = $this->groupByTreatKey($drugList);

        $startNormalized = $this->normalizeDate($startDate);
        $endNormalized = $this->normalizeDate($endDate);

        $savedCount = 0;

        DB::transaction(function () use (
            $customerId,
            $basicList,
            $detailGroup,
            $drugGroup,
            $startNormalized,
            $endNormalized,
            &$savedCount
        ) {
            // 1) 동기화 구간 내 HIRA source row 삭제 (재동기화 안전)
            MedicalRecord::where('customer_id', $customerId)
                ->where('source', MedicalRecord::SOURCE_CODEF_HIRA)
                ->when($startNormalized, fn ($q) => $q->where('treatment_date', '>=', $startNormalized))
                ->when($endNormalized, fn ($q) => $q->where('treatment_date', '<=', $endNormalized))
                ->delete();

            // 2) basicList → row insert
            foreach ($basicList as $basic) {
                $treatDate = $this->normalizeDate($basic['resTreatStartDate'] ?? null);
                $hospitalName = $basic['resHospitalName'] ?? '';

                if ($treatDate === null) {
                    continue;
                }

                $key = ($basic['resTreatStartDate'] ?? '') . '|' . $hospitalName;

                MedicalRecord::create([
                    'customer_id' => $customerId,
                    'treatment_date' => $treatDate,
                    'hospital_name' => $hospitalName ?: null,
                    'department' => $basic['resDepartment'] ?? null,
                    'diagnosis_code' => $basic['resDiseaseCode'] ?? null,
                    'diagnosis_name' => $basic['resDiseaseName'] ?? null,
                    'treatment_type' => $basic['resTreatType'] ?? null,
                    'medical_cost' => $this->parseDecimal($basic['resTotalAmount'] ?? null),

                    'visit_days' => isset($basic['resVisitDays']) ? (int) $basic['resVisitDays'] : null,
                    'total_amount' => $this->parseDecimal($basic['resTotalAmount'] ?? null),
                    'public_charge' => $this->parseDecimal($basic['resPublicCharge'] ?? null),
                    'deductible_amt' => $this->parseDecimal($basic['resDeductibleAmt'] ?? null),
                    'hospital_code' => $basic['resHospitalCode'] ?? null,

                    'detail_treat_list_json' => isset($detailGroup[$key])
                        ? json_encode($detailGroup[$key], JSON_UNESCAPED_UNICODE)
                        : null,
                    'prescribe_drug_list_json' => isset($drugGroup[$key])
                        ? json_encode($drugGroup[$key], JSON_UNESCAPED_UNICODE)
                        : null,

                    'codef_synced' => true,
                    'synced_at' => now(),
                    'source' => MedicalRecord::SOURCE_CODEF_HIRA,
                    'codef_raw_data' => json_encode($basic, JSON_UNESCAPED_UNICODE),
                ]);

                $savedCount++;
            }

            // 3) health_external_account 동기화 메타 갱신
            HealthExternalAccount::updateOrCreate(
                ['customer_id' => $customerId],
                [
                    'last_medical_info_sync_at' => now(),
                    'medical_info_range_start' => $startNormalized,
                    'medical_info_range_end' => $endNormalized,
                ]
            );
        });

        Log::info('HIRA 내진료정보 저장 완료', [
            'customer_id' => $customerId,
            'saved_count' => $savedCount,
            'range' => $startNormalized . ' ~ ' . $endNormalized,
        ]);

        return $savedCount;
    }

    // ========================================================================
    // 3. 안전장치
    // ========================================================================

    /**
     * 24h 쿨다운 체크 (IP 차단 방지)
     */
    public function isRateLimited(string $customerId): bool
    {
        $account = HealthExternalAccount::where('customer_id', $customerId)->first();
        if (!$account || !$account->last_medical_info_sync_at) {
            return false;
        }

        return $account->last_medical_info_sync_at->diffInSeconds(now()) < self::SYNC_COOLDOWN_SECONDS;
    }

    /**
     * 주민등록번호 정합성 검증
     * - 13자리 숫자 형식
     * - 체크섬 검증
     */
    protected function validateIdentity(?string $identity): bool
    {
        if (empty($identity)) {
            return false;
        }

        $digits = preg_replace('/\D/', '', $identity);
        if (strlen($digits) !== 13) {
            return false;
        }

        // 체크섬 검증
        $weights = [2, 3, 4, 5, 6, 7, 8, 9, 2, 3, 4, 5];
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += ((int) $digits[$i]) * $weights[$i];
        }
        $checkDigit = (11 - ($sum % 11)) % 10;

        return $checkDigit === (int) $digits[12];
    }

    // ========================================================================
    // 헬퍼
    // ========================================================================

    /**
     * 요청 데이터 + Customer 폴백으로 HIRA 인증 파라미터 빌드
     *
     * loginType="5" (간편인증) 기본
     * HIRA는 birthDate 불필요 (identity 13자리로 충분)
     *
     * @param array $data [customer?, loginTypeLevel, userName, identity, phoneNo, telecom]
     */
    protected function buildAuthParams(array $data): array
    {
        /** @var Customer|null $customer */
        $customer = $data['customer'] ?? null;

        $userName = $data['userName'] ?? $customer?->name;
        $identity = $data['identity'] ?? $customer?->resident_number;
        $phoneNo = $data['phoneNo'] ?? $customer?->phone;
        $telecom = $data['telecom'] ?? $customer?->telecom;

        if (empty($identity) || empty($userName) || empty($phoneNo) || empty($telecom)) {
            throw new \RuntimeException('인증정보(이름/주민번호/전화번호/통신사)를 입력해주세요.');
        }

        return [
            'organization' => $this->organization,
            'loginType' => '5',
            'loginTypeLevel' => (string) $data['loginTypeLevel'],
            'userName' => $userName,
            // identity는 평문 13자리 전송 (CODEF 기본 방식)
            'identity' => preg_replace('/\D/', '', $identity),
            'phoneNo' => preg_replace('/\D/', '', $phoneNo),
            'telecom' => (string) $telecom,
            'id' => $customer?->customer_id ?? ($data['customer_id'] ?? null),
        ];
    }

    /**
     * (treatStartDate|hospitalName) 키로 리스트 그룹화
     */
    protected function groupByTreatKey(array $list): array
    {
        $grouped = [];
        foreach ($list as $item) {
            $key = ($item['resTreatStartDate'] ?? '') . '|' . ($item['resHospitalName'] ?? '');
            $grouped[$key][] = $item;
        }
        return $grouped;
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
     * 숫자 문자열 → decimal (콤마 제거)
     */
    protected function parseDecimal(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $cleaned = preg_replace('/[^0-9.\-]/', '', $value);
        return $cleaned === '' ? null : $cleaned;
    }

    /**
     * 기본 조회 구간 생성 (오늘 기준 5년 전 ~ 오늘)
     */
    public static function defaultDateRange(): array
    {
        $now = Carbon::now();
        return [
            'start' => $now->copy()->subYears(5)->format('Ymd'),
            'end' => $now->format('Ymd'),
        ];
    }

    /**
     * 2-Way 응답 여부 확인
     */
    private function isTwoWay(array $result): bool
    {
        return ($result['two_way'] ?? false) === true;
    }
}
