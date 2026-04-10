<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthCheckup extends Model
{
    protected $table = 'health_checkup';
    protected $primaryKey = 'checkup_id';

    protected $fillable = [
        'customer_id',
        'checkup_date',
        'checkup_year',
        'checkup_type',
        'hospital_name',
        'organization_name',
        'opinion',
        'judgement',
        // 신체계측
        'height', 'weight', 'waist', 'bmi', 'sight', 'hearing',
        // 혈압/요/혈색소
        'blood_pressure', 'urinary_protein', 'hemoglobin',
        // 혈당/지질
        'fasting_blood_sugar', 'total_cholesterol', 'hdl_cholesterol',
        'ldl_cholesterol', 'triglyceride',
        // 신장/간
        'serum_creatinine', 'gfr', 'ast', 'alt', 'y_gtp',
        // 기타
        'tb_chest_disease', 'osteoporosis',
        // 문진
        'question_info_json',
        // 레거시
        'checkup_results', 'abnormal_findings', 'follow_up_required',
        // CODEF 메타
        'codef_synced', 'synced_at', 'codef_raw_data',
    ];

    protected $casts = [
        'checkup_date' => 'date',
        'follow_up_required' => 'boolean',
        'codef_synced' => 'boolean',
        'synced_at' => 'datetime',
    ];

    protected $appends = ['risk_summary'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    /**
     * 위험지표 요약 (KDCA 가이드 기준)
     * 각 항목별 정상/주의/이상 판정 및 카운트
     */
    public function getRiskSummaryAttribute(): array
    {
        $items = [];

        // 혈압
        if ($this->blood_pressure) {
            $items['blood_pressure'] = $this->evaluateBloodPressure($this->blood_pressure);
        }
        // 공복혈당
        if ($this->fasting_blood_sugar !== null && $this->fasting_blood_sugar !== '') {
            $items['fasting_blood_sugar'] = $this->evaluateNumeric(
                (float) $this->fasting_blood_sugar,
                normal: [0, 100],
                caution: [100, 126],
                danger: [126, INF]
            );
        }
        // 총콜레스테롤
        if ($this->total_cholesterol !== null && $this->total_cholesterol !== '') {
            $items['total_cholesterol'] = $this->evaluateNumeric(
                (float) $this->total_cholesterol,
                normal: [0, 200],
                caution: [200, 240],
                danger: [240, INF]
            );
        }
        // HDL (높을수록 좋음)
        if ($this->hdl_cholesterol !== null && $this->hdl_cholesterol !== '') {
            $val = (float) $this->hdl_cholesterol;
            $items['hdl_cholesterol'] = $val < 40 ? 'danger' : ($val < 60 ? 'caution' : 'normal');
        }
        // LDL
        if ($this->ldl_cholesterol !== null && $this->ldl_cholesterol !== '') {
            $items['ldl_cholesterol'] = $this->evaluateNumeric(
                (float) $this->ldl_cholesterol,
                normal: [0, 130],
                caution: [130, 160],
                danger: [160, INF]
            );
        }
        // 중성지방
        if ($this->triglyceride !== null && $this->triglyceride !== '') {
            $items['triglyceride'] = $this->evaluateNumeric(
                (float) $this->triglyceride,
                normal: [0, 150],
                caution: [150, 200],
                danger: [200, INF]
            );
        }
        // BMI (양방향)
        if ($this->bmi !== null && $this->bmi !== '') {
            $val = (float) $this->bmi;
            if ($val < 18.5) {
                $items['bmi'] = 'caution';
            } elseif ($val < 23) {
                $items['bmi'] = 'normal';
            } elseif ($val < 25) {
                $items['bmi'] = 'caution';
            } else {
                $items['bmi'] = 'danger';
            }
        }
        // AST
        if ($this->ast !== null && $this->ast !== '') {
            $items['ast'] = $this->evaluateNumeric((float) $this->ast, [0, 40], [40, 80], [80, INF]);
        }
        // ALT
        if ($this->alt !== null && $this->alt !== '') {
            $items['alt'] = $this->evaluateNumeric((float) $this->alt, [0, 40], [40, 80], [80, INF]);
        }
        // GFR (낮을수록 나쁨)
        if ($this->gfr !== null && $this->gfr !== '') {
            $val = (float) $this->gfr;
            $items['gfr'] = $val < 60 ? 'danger' : ($val < 90 ? 'caution' : 'normal');
        }

        $counts = ['normal' => 0, 'caution' => 0, 'danger' => 0];
        foreach ($items as $level) {
            $counts[$level] = ($counts[$level] ?? 0) + 1;
        }

        $overall = $counts['danger'] > 0 ? 'danger' : ($counts['caution'] > 0 ? 'caution' : 'normal');

        return [
            'items' => $items,
            'counts' => $counts,
            'overall' => $overall,
        ];
    }

    /**
     * 혈압 문자열 (ex: "120/80") 평가
     */
    private function evaluateBloodPressure(string $bp): string
    {
        if (!preg_match('/(\d+)\s*\/\s*(\d+)/', $bp, $m)) {
            return 'normal';
        }
        $sys = (int) $m[1];
        $dia = (int) $m[2];

        if ($sys >= 140 || $dia >= 90) return 'danger';
        if ($sys >= 120 || $dia >= 80) return 'caution';
        return 'normal';
    }

    /**
     * 수치 평가: normal (lo<=v<hi)
     */
    private function evaluateNumeric(float $value, array $normal, array $caution, array $danger): string
    {
        if ($value >= $danger[0]) return 'danger';
        if ($value >= $caution[0]) return 'caution';
        return 'normal';
    }

    public function scopeLatestForCustomer($query, string $customerId)
    {
        return $query->where('customer_id', $customerId)
            ->orderByDesc('checkup_date')
            ->orderByDesc('checkup_id');
    }
}
