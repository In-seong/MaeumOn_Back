<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthPrediction extends Model
{
    protected $table = 'health_prediction';
    protected $primaryKey = 'prediction_id';

    // 예측 유형
    const TYPE_CARDIO = 'cardio';        // 심뇌혈관
    const TYPE_STROKE = 'stroke';        // 뇌졸중
    const TYPE_DIABETES = 'diabetes';    // 당뇨
    const TYPE_KIDNEY = 'kidney';        // 만성신장
    const TYPE_MI = 'mi';                // 심근경색
    const TYPE_HEALTH_AGE = 'health_age'; // 건강나이

    const PREDICTION_TYPES = [
        self::TYPE_CARDIO,
        self::TYPE_STROKE,
        self::TYPE_DIABETES,
        self::TYPE_KIDNEY,
        self::TYPE_MI,
    ];

    protected $fillable = [
        'customer_id',
        'prediction_type',
        'checkup_date',
        'risk_grade',
        'risk_ratio',
        'average_age',
        'average_ratio',
        'health_age',
        'chronological_age',
        'change_after_text',
        'detail_list_json',
        'sub_detail_list_json',
        'compare_list_json',
        'codef_raw_data',
        'predicted_at',
    ];

    protected $casts = [
        'checkup_date' => 'date',
        'predicted_at' => 'datetime',
    ];

    protected $appends = ['detail_list', 'sub_detail_list', 'compare_list', 'risk_grade_label'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    /**
     * 위험요인 분석 (detailList) 디코드
     */
    public function getDetailListAttribute(): array
    {
        return $this->detail_list_json ? (json_decode($this->detail_list_json, true) ?? []) : [];
    }

    /**
     * 처방 메시지 (subDetailList) 디코드
     */
    public function getSubDetailListAttribute(): array
    {
        return $this->sub_detail_list_json ? (json_decode($this->sub_detail_list_json, true) ?? []) : [];
    }

    /**
     * 비교 데이터 (compareList) 디코드
     */
    public function getCompareListAttribute(): array
    {
        return $this->compare_list_json ? (json_decode($this->compare_list_json, true) ?? []) : [];
    }

    /**
     * 위험등급 라벨
     */
    public function getRiskGradeLabelAttribute(): string
    {
        return match ((string) $this->risk_grade) {
            '1' => '잘하고있어요',
            '2' => '양호',
            '3' => '주의',
            '4' => '경계',
            '5' => '관리필요',
            default => '미평가',
        };
    }

    public function scopeForCustomer($query, string $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('prediction_type', $type);
    }

    public function scopeLatest($query)
    {
        return $query->orderByDesc('predicted_at')->orderByDesc('prediction_id');
    }
}
