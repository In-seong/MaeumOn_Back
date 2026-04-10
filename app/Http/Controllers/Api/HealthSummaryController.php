<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HealthCheckup;
use App\Models\HealthPrediction;
use App\Models\MedicalRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 건강 종합 요약 컨트롤러 (대시보드용)
 *
 * 한 번의 호출로 다음을 반환:
 * - 최근 검진결과 (위험지표 요약 포함)
 * - 6종 예측 (cardio/stroke/diabetes/kidney/mi/health_age)
 * - HIRA 진료내역 카운트
 * - 동의/동기화 상태
 */
class HealthSummaryController extends Controller
{
    /**
     * GET /api/health/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $customer = $request->user()->customer;
        $customerId = $customer->customer_id;

        // 최근 검진 (risk_summary accessor 자동 포함)
        $latestCheckup = HealthCheckup::latestForCustomer($customerId)->first();

        // 예측 6종 (각 type별 최신 1건)
        $predictions = HealthPrediction::forCustomer($customerId)
            ->orderByDesc('predicted_at')
            ->get()
            ->groupBy('prediction_type')
            ->map(fn ($group) => $group->first());

        // HIRA 진료내역
        $medicalCount = MedicalRecord::forCustomer($customerId)->fromHira()->count();
        $latestMedical = MedicalRecord::forCustomer($customerId)
            ->fromHira()
            ->orderByDesc('treatment_date')
            ->limit(5)
            ->get();

        $account = $customer->healthExternalAccount;

        return response()->json([
            'success' => true,
            'data' => [
                'consent' => [
                    'health_checkup' => (bool) $account?->checkup_consent_at,
                    'medical_info' => (bool) $account?->medical_info_consent_at,
                    'health_in_linked' => (bool) $account?->health_in_linked,
                ],
                'sync' => [
                    'last_checkup_sync_at' => $account?->last_checkup_sync_at,
                    'last_medical_info_sync_at' => $account?->last_medical_info_sync_at,
                    'last_examination_sync_at' => $account?->last_examination_sync_at,
                    'last_health_age_sync_at' => $account?->last_health_age_sync_at,
                    'last_prediction_sync_at' => $account?->last_prediction_sync_at,
                ],
                'checkup' => [
                    'latest' => $latestCheckup,
                    'risk_summary' => $latestCheckup?->risk_summary,
                ],
                'predictions' => $predictions,
                'medical' => [
                    'count' => $medicalCount,
                    'latest' => $latestMedical,
                ],
            ],
        ]);
    }
}
