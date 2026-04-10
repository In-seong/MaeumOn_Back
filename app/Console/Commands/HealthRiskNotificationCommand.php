<?php

namespace App\Console\Commands;

use App\Models\HealthCheckup;
use App\Models\HealthPrediction;
use App\Services\Health\HealthRiskEvaluator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * 건강 위험지표 일일 배치
 *
 * - 어제 동기화된 health_checkup → 위험 평가 → 알림
 * - 어제 동기화된 health_prediction → risk_grade>=4 → 알림
 *
 * 스케줄: 매일 09:00
 */
class HealthRiskNotificationCommand extends Command
{
    protected $signature = 'health:notify-risks';
    protected $description = '건강검진 + 예측 결과의 위험지표를 평가하여 푸시 알림 발송';

    protected HealthRiskEvaluator $evaluator;

    public function __construct(HealthRiskEvaluator $evaluator)
    {
        parent::__construct();
        $this->evaluator = $evaluator;
    }

    public function handle(): int
    {
        $since = Carbon::now()->subDay();

        $checkupCount = $this->processCheckups($since);
        $predictionCount = $this->processPredictions($since);

        $this->info("HealthRiskNotificationCommand 완료: 검진 {$checkupCount}건, 예측 {$predictionCount}건 알림");

        return self::SUCCESS;
    }

    /**
     * 어제 동기화된 검진결과를 평가하여 알림 발송
     */
    private function processCheckups(Carbon $since): int
    {
        $checkups = HealthCheckup::where('synced_at', '>=', $since)
            ->where('codef_synced', true)
            ->get();

        $notified = 0;
        foreach ($checkups as $checkup) {
            if ($this->evaluator->evaluateCheckup($checkup)) {
                $notified++;
            }
        }

        return $notified;
    }

    /**
     * 어제 저장된 예측결과를 평가하여 알림 발송
     */
    private function processPredictions(Carbon $since): int
    {
        $predictions = HealthPrediction::where('predicted_at', '>=', $since)
            ->whereIn('prediction_type', HealthPrediction::PREDICTION_TYPES)
            ->get();

        $notified = 0;
        foreach ($predictions as $prediction) {
            if ($this->evaluator->evaluatePrediction($prediction)) {
                $notified++;
            }
        }

        return $notified;
    }
}
