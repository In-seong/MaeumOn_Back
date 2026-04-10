<?php

namespace App\Services\Health;

use App\Models\Customer;
use App\Models\HealthCheckup;
use App\Models\HealthPrediction;
use App\Models\Notification;
use App\Services\FcmService;
use Illuminate\Support\Facades\Log;

/**
 * 건강 위험지표 평가 + 알림 발송
 *
 * 트리거:
 * 1. HealthCheckup 저장 시 (Observer): risk_summary.overall=danger 또는 caution≥3
 * 2. HealthPrediction 저장 시 (Observer): risk_grade>=4
 * 3. 일일 배치 (HealthRiskNotificationCommand): 위 조건을 모든 신규 데이터에 대해 검사
 */
class HealthRiskEvaluator
{
    protected FcmService $fcmService;

    public function __construct(FcmService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * 건강검진 결과 평가 → 알림 발송
     *
     * @return bool 알림 발송 여부
     */
    public function evaluateCheckup(HealthCheckup $checkup): bool
    {
        $summary = $checkup->risk_summary;
        if (!$summary) {
            return false;
        }

        $overall = $summary['overall'] ?? 'normal';
        $cautionCount = $summary['counts']['caution'] ?? 0;
        $dangerCount = $summary['counts']['danger'] ?? 0;

        // 알림 조건: danger 1개 이상 OR caution 3개 이상
        if ($dangerCount === 0 && $cautionCount < 3) {
            return false;
        }

        $customer = $checkup->customer;
        if (!$customer) {
            return false;
        }

        $title = '⚠️ 건강검진 위험지표 알림';
        $abnormalLabel = $this->summarizeAbnormalItems($summary['items'] ?? []);
        $body = $abnormalLabel
            ? "{$abnormalLabel} 항목이 정상 범위를 벗어났습니다. 자세히 보기 →"
            : '건강검진 결과 일부 항목에 주의가 필요합니다. 자세히 보기 →';

        return $this->dispatchNotifications($customer, $title, $body, 'health_checkup_risk');
    }

    /**
     * 예측 결과 평가 → 알림 발송
     */
    public function evaluatePrediction(HealthPrediction $prediction): bool
    {
        // 건강나이는 예측 알림 제외
        if ($prediction->prediction_type === HealthPrediction::TYPE_HEALTH_AGE) {
            return false;
        }

        $grade = (int) ($prediction->risk_grade ?? 0);
        if ($grade < 4) {
            return false;
        }

        $customer = $prediction->customer;
        if (!$customer) {
            return false;
        }

        $typeLabel = $this->predictionTypeLabel($prediction->prediction_type);
        $gradeLabel = $prediction->risk_grade_label;

        $title = '⚠️ 질환 예측 위험 알림';
        $body = "{$typeLabel} 위험등급이 '{$gradeLabel}'로 평가되었습니다. 자세히 보기 →";

        return $this->dispatchNotifications($customer, $title, $body, 'health_prediction_risk');
    }

    // ========================================================================
    // 내부 헬퍼
    // ========================================================================

    /**
     * 고객 + 담당 설계사에게 알림 발송
     */
    protected function dispatchNotifications(Customer $customer, string $title, string $body, string $notificationType): bool
    {
        try {
            // 1) 고객 in-app notification
            Notification::create([
                'receiver_id' => $customer->customer_id,
                'receiver_type' => 'CUSTOMER',
                'sender_type' => 'SYSTEM',
                'notification_type' => $notificationType,
                'title' => $title,
                'content' => $body,
                'is_read' => false,
                'sent_at' => now(),
                'created_at' => now(),
            ]);

            // 2) 고객 FCM
            $this->fcmService->sendToUsers('CUSTOMER', [$customer->customer_id], $title, $body);

            // 3) 담당 설계사에게도 알림 (있는 경우)
            if ($customer->agent_id) {
                $agentBody = "담당 고객 [{$customer->name}] · {$body}";

                Notification::create([
                    'receiver_id' => $customer->agent_id,
                    'receiver_type' => 'AGENT',
                    'sender_type' => 'SYSTEM',
                    'notification_type' => $notificationType,
                    'title' => $title,
                    'content' => $agentBody,
                    'is_read' => false,
                    'sent_at' => now(),
                ]);

                $this->fcmService->sendToUsers('AGENT', [$customer->agent_id], $title, $agentBody);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('건강 위험 알림 발송 실패', [
                'customer_id' => $customer->customer_id,
                'type' => $notificationType,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 위험 항목 라벨 요약
     */
    protected function summarizeAbnormalItems(array $items): string
    {
        $labels = [
            'blood_pressure' => '혈압',
            'fasting_blood_sugar' => '공복혈당',
            'total_cholesterol' => '총콜레스테롤',
            'hdl_cholesterol' => 'HDL',
            'ldl_cholesterol' => 'LDL',
            'triglyceride' => '중성지방',
            'bmi' => 'BMI',
            'ast' => 'AST',
            'alt' => 'ALT',
            'gfr' => 'GFR',
        ];

        $abnormal = [];
        foreach ($items as $key => $level) {
            if ($level === 'danger') {
                $abnormal[] = $labels[$key] ?? $key;
            }
        }

        if (empty($abnormal)) {
            // danger가 없으면 caution도 표시
            foreach ($items as $key => $level) {
                if ($level === 'caution') {
                    $abnormal[] = $labels[$key] ?? $key;
                }
            }
        }

        return implode(', ', array_slice($abnormal, 0, 3));
    }

    protected function predictionTypeLabel(string $type): string
    {
        return match ($type) {
            HealthPrediction::TYPE_CARDIO => '심뇌혈관 질환',
            HealthPrediction::TYPE_STROKE => '뇌졸중',
            HealthPrediction::TYPE_DIABETES => '당뇨병',
            HealthPrediction::TYPE_KIDNEY => '만성신장질환',
            HealthPrediction::TYPE_MI => '심근경색',
            default => '질환',
        };
    }
}
