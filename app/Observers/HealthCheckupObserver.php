<?php

namespace App\Observers;

use App\Models\HealthCheckup;
use App\Services\Health\HealthRiskEvaluator;
use Illuminate\Support\Facades\Log;

/**
 * HealthCheckup 저장 시 자동 위험지표 평가 + 알림
 */
class HealthCheckupObserver
{
    protected HealthRiskEvaluator $evaluator;

    public function __construct(HealthRiskEvaluator $evaluator)
    {
        $this->evaluator = $evaluator;
    }

    /**
     * 신규 검진결과 저장 후 평가
     */
    public function created(HealthCheckup $checkup): void
    {
        $this->evaluate($checkup);
    }

    /**
     * 기존 검진 갱신 시: 주요 수치가 변경된 경우에만 재평가
     */
    public function updated(HealthCheckup $checkup): void
    {
        $watchedFields = [
            'blood_pressure', 'fasting_blood_sugar',
            'total_cholesterol', 'hdl_cholesterol', 'ldl_cholesterol', 'triglyceride',
            'bmi', 'ast', 'alt', 'gfr',
        ];

        $changed = false;
        foreach ($watchedFields as $field) {
            if ($checkup->wasChanged($field)) {
                $changed = true;
                break;
            }
        }

        if ($changed) {
            $this->evaluate($checkup);
        }
    }

    private function evaluate(HealthCheckup $checkup): void
    {
        try {
            $this->evaluator->evaluateCheckup($checkup);
        } catch (\Exception $e) {
            Log::error('HealthCheckupObserver 평가 실패', [
                'checkup_id' => $checkup->checkup_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
