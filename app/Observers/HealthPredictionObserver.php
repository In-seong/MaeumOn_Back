<?php

namespace App\Observers;

use App\Models\HealthPrediction;
use App\Services\Health\HealthRiskEvaluator;
use Illuminate\Support\Facades\Log;

/**
 * HealthPrediction 저장 시 risk_grade>=4 알림
 */
class HealthPredictionObserver
{
    protected HealthRiskEvaluator $evaluator;

    public function __construct(HealthRiskEvaluator $evaluator)
    {
        $this->evaluator = $evaluator;
    }

    public function created(HealthPrediction $prediction): void
    {
        $this->evaluate($prediction);
    }

    public function updated(HealthPrediction $prediction): void
    {
        // risk_grade가 변경된 경우만 재평가
        if ($prediction->wasChanged('risk_grade')) {
            $this->evaluate($prediction);
        }
    }

    private function evaluate(HealthPrediction $prediction): void
    {
        try {
            $this->evaluator->evaluatePrediction($prediction);
        } catch (\Exception $e) {
            Log::error('HealthPredictionObserver 평가 실패', [
                'prediction_id' => $prediction->prediction_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
