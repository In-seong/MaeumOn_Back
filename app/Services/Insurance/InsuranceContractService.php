<?php

namespace App\Services\Insurance;

use App\Models\Insurance;
use App\Models\InsuranceCompany;
use App\Models\InsuranceCoverage;
use App\Models\InsurancePaymentHistory;
use App\Models\InsuranceStatistics;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InsuranceContractService
{
    /** CODEF 계약 유형 → contract_type 매핑 */
    private const CONTRACT_TYPE_MAP = [
        'resSavingsContractList' => 'savings',
        'resCarContractList' => 'car',
        'resPropertyContractList' => 'property',
        'resActualLossContractList' => 'actual_loss',
        'resFlatRateContractList' => 'flat_rate',
    ];

    /** CODEF 계약 유형 → insurance_type 한글명 */
    private const INSURANCE_TYPE_MAP = [
        'savings' => '저축성보험',
        'car' => '자동차보험',
        'property' => '화재특종보장',
        'actual_loss' => '실손형보장',
        'flat_rate' => '정액형보장',
    ];

    /**
     * CODEF 계약정보 API 응답 전체를 파싱하여 DB에 저장
     */
    public function syncContracts(string $customerId, array $apiResponse): void
    {
        DB::beginTransaction();

        try {
            // 5가지 계약 유형 동기화
            foreach (self::CONTRACT_TYPE_MAP as $responseKey => $contractType) {
                $contracts = $apiResponse[$responseKey] ?? [];
                if (!empty($contracts)) {
                    $this->syncContractsByType($customerId, $contractType, $contracts);
                }
            }

            // 실손형 지급내역
            $payments = $apiResponse['resActualLossPaymentList'] ?? [];
            if (!empty($payments)) {
                $this->syncPaymentHistory($customerId, $payments);
            }

            // 분석통계 - 정액형
            $flatRateStats = $apiResponse['resFlatRateStatisticsList'] ?? [];
            if (!empty($flatRateStats)) {
                $this->syncStatistics($customerId, $flatRateStats, 'flat_rate');
            }

            // 분석통계 - 실손형
            $actualLossStats = $apiResponse['resActualLossStatisticsList'] ?? [];
            if (!empty($actualLossStats)) {
                $this->syncStatistics($customerId, $actualLossStats, 'actual_loss');
            }

            DB::commit();

            Log::info('CODEF 계약정보 DB 동기화 완료', [
                'customer_id' => $customerId,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CODEF 계약정보 동기화 실패', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 유형별 계약 목록을 DB에 upsert (policy_number 기준)
     */
    public function syncContractsByType(string $customerId, string $contractType, array $contracts): void
    {
        foreach ($contracts as $contract) {
            $policyNumber = $contract['resPolicyNumber'] ?? null;
            if (!$policyNumber) {
                Log::warning('CODEF 계약: 증권번호 없음, 건너뜀', [
                    'customer_id' => $customerId,
                    'contract_type' => $contractType,
                ]);
                continue;
            }

            // 보험사 매칭
            $companyName = $contract['resCompanyNm'] ?? '';
            $companyId = $this->resolveCompanyId($companyName);

            // insurance upsert (customer_id + policy_number 기준)
            $insurance = Insurance::updateOrCreate(
                [
                    'customer_id' => $customerId,
                    'policy_number' => $policyNumber,
                ],
                [
                    'company_id' => $companyId,
                    'insurance_type' => self::INSURANCE_TYPE_MAP[$contractType] ?? $contractType,
                    'product_name' => $contract['resInsuranceName'] ?? null,
                    'coverage_amount' => $this->parseDecimal($contract['resCoverageAmount'] ?? null),
                    'premium_amount' => $this->parseDecimal($contract['resPremium'] ?? null),
                    'payment_period' => $contract['resPaymentCycle'] ?? null,
                    'subscription_date' => $this->parseDate($contract['resSubscriptionDate'] ?? ($contract['commStartDate'] ?? null)),
                    'expiration_date' => $this->parseDate($contract['commEndDate'] ?? null),
                    'is_active' => 1,
                    // CODEF 추가 컬럼
                    'contract_type' => $contractType,
                    'contractor_name' => $contract['resContractor'] ?? null,
                    'contract_status' => $contract['resContractStatus'] ?? null,
                    'company_phone' => $contract['resPhoneNo'] ?? null,
                    'company_homepage' => $contract['resHomePage'] ?? null,
                    'payment_cycle' => $contract['resPaymentCycle'] ?? null,
                    'start_date' => $this->parseDate($contract['commStartDate'] ?? null),
                    'end_date' => $this->parseDate($contract['commEndDate'] ?? null),
                    'insured_person' => $contract['resInsuredPerson'] ?? null,
                    'res_number' => $contract['resNumber'] ?? null,
                    'contract_date_of' => $this->parseDate($contract['resDateOfContract'] ?? null),
                    'car_name' => $contract['commCarName'] ?? null,
                    'car_number' => $contract['resCarNo'] ?? null,
                    'codef_synced' => 1,
                    'synced_at' => now(),
                    'codef_raw_data' => json_encode($contract, JSON_UNESCAPED_UNICODE),
                ]
            );

            // 보장내역 동기화
            $coverages = $contract['resCoverageLists'] ?? [];
            if (!empty($coverages)) {
                $this->syncCoverages($insurance->insurance_id, $coverages);
            }
        }
    }

    /**
     * 보장내역 동기화: 기존 삭제 후 재생성
     */
    public function syncCoverages(int $insuranceId, array $coverages): void
    {
        // 기존 보장내역 삭제
        InsuranceCoverage::where('insurance_id', $insuranceId)->delete();

        // 새로 생성
        foreach ($coverages as $coverage) {
            InsuranceCoverage::create([
                'insurance_id' => $insuranceId,
                'insured_person' => $coverage['resInsuredPerson'] ?? null,
                'coverage_name' => $coverage['resCoverageName'] ?? '(보장명 없음)',
                'coverage_amount' => $this->parseDecimal($coverage['resCoverageAmount'] ?? null),
                'coverage_status' => $coverage['resCoverageStatus'] ?? null,
                'agreement_type' => $coverage['resAgreementType'] ?? null,
                'coverage_type' => $coverage['resType'] ?? null,
                'object_info' => $coverage['resObject'] ?? null,
                'zip_code' => $coverage['resZipCode'] ?? null,
            ]);
        }
    }

    /**
     * 실손형 지급내역 동기화
     */
    public function syncPaymentHistory(string $customerId, array $payments): void
    {
        // 기존 지급내역 삭제 후 재생성
        InsurancePaymentHistory::where('customer_id', $customerId)->delete();

        foreach ($payments as $payment) {
            $policyNumber = $payment['resPolicyNumber'] ?? null;
            $companyName = $payment['resCompanyNm'] ?? null;

            // insurance_id 매칭 (증권번호 기준)
            $insuranceId = null;
            if ($policyNumber) {
                $insurance = Insurance::where('customer_id', $customerId)
                    ->where('policy_number', $policyNumber)
                    ->first();
                $insuranceId = $insurance?->insurance_id;
            }

            // 상세 지급내역 (resDetailList)
            $detailList = $payment['resDetailList'] ?? [];

            if (!empty($detailList)) {
                foreach ($detailList as $detail) {
                    InsurancePaymentHistory::create([
                        'customer_id' => $customerId,
                        'insurance_id' => $insuranceId,
                        'company_name' => $companyName,
                        'policy_number' => $policyNumber,
                        'insurance_name' => $payment['resInsuranceName'] ?? null,
                        'res_number' => $payment['resNumber'] ?? null,
                        'occur_date_time' => $payment['resOccurDateTime'] ?? null,
                        'total_amount' => $this->parseDecimal($payment['resTotalAmount'] ?? null),
                        'payment_type' => $detail['resType'] ?? null,
                        'reason' => $detail['resReasonForPayment'] ?? null,
                        'paid_amount' => $this->parseDecimal($detail['resPaidAmount'] ?? null),
                        'payment_date' => $this->parseDate($detail['resPaymentDate'] ?? null),
                        'judge_result' => $detail['resJudgeResult'] ?? null,
                    ]);
                }
            } else {
                // 상세가 없으면 상위 레벨만 저장
                InsurancePaymentHistory::create([
                    'customer_id' => $customerId,
                    'insurance_id' => $insuranceId,
                    'company_name' => $companyName,
                    'policy_number' => $policyNumber,
                    'insurance_name' => $payment['resInsuranceName'] ?? null,
                    'res_number' => $payment['resNumber'] ?? null,
                    'occur_date_time' => $payment['resOccurDateTime'] ?? null,
                    'total_amount' => $this->parseDecimal($payment['resTotalAmount'] ?? null),
                ]);
            }
        }
    }

    /**
     * 분석통계 동기화
     */
    public function syncStatistics(string $customerId, array $stats, string $statType): void
    {
        // 해당 유형 기존 통계 삭제 후 재생성
        InsuranceStatistics::where('customer_id', $customerId)
            ->where('stat_type', $statType)
            ->delete();

        foreach ($stats as $stat) {
            InsuranceStatistics::create([
                'customer_id' => $customerId,
                'stat_type' => $statType,
                'coverage_name' => $stat['resCoverageName'] ?? '(보장명 없음)',
                'self_coverage_amt' => $this->parseDecimal($stat['resSelfCoverageAmt'] ?? null),
                'avg_group_coverage_amt' => $this->parseDecimal($stat['resAvgGroupCoverageAmt'] ?? null),
                'self_reg_yn' => $stat['resSelfRegYN'] ?? null,
                'avg_group_reg_rate' => $stat['resAvgGroupRegRate'] ?? null,
                'synced_at' => now(),
            ]);
        }
    }

    /**
     * 고객별 보험 목록 조회 (보장내역 eager load)
     */
    public function getCustomerContracts(string $customerId): Collection
    {
        return Insurance::where('customer_id', $customerId)
            ->where('is_active', true)
            ->with(['coverages', 'insuranceCompany:company_id,company_name,company_code,logo_path'])
            ->orderBy('subscription_date', 'desc')
            ->get();
    }

    /**
     * 보험 상세 + 보장내역
     */
    public function getContractDetail(int $insuranceId): ?Insurance
    {
        return Insurance::with(['coverages', 'insuranceCompany', 'paymentHistories'])
            ->find($insuranceId);
    }

    /**
     * 보험사명으로 company_id 찾기 (없으면 자동 생성)
     */
    private function resolveCompanyId(string $companyName): ?int
    {
        if (empty($companyName)) {
            return null;
        }

        $company = InsuranceCompany::where('company_name', $companyName)->first();

        if ($company) {
            return $company->company_id;
        }

        // 자동 생성
        Log::info('CODEF: 신규 보험사 자동 생성', ['company_name' => $companyName]);

        $newCompany = InsuranceCompany::create([
            'company_name' => $companyName,
            'is_active' => 1,
        ]);

        return $newCompany->company_id;
    }

    /**
     * CODEF 날짜 문자열(yyyyMMdd) → date 변환
     */
    private function parseDate(?string $dateStr): ?string
    {
        if (empty($dateStr)) {
            return null;
        }

        // yyyyMMdd 형식 → Y-m-d
        $dateStr = preg_replace('/[^0-9]/', '', $dateStr);

        if (strlen($dateStr) === 8) {
            $parsed = \DateTime::createFromFormat('Ymd', $dateStr);
            if ($parsed && $parsed->format('Ymd') === $dateStr) {
                return $parsed->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * 숫자 문자열 → decimal 변환
     */
    private function parseDecimal(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        // 콤마, 공백 제거
        $cleaned = preg_replace('/[^0-9.\-]/', '', $value);

        return is_numeric($cleaned) ? (float) $cleaned : null;
    }
}
