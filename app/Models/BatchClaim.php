<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BatchClaim extends Model
{
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_PARTIAL_FAILED = 'partial_failed';

    const VALID_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_COMPLETED,
        self::STATUS_PARTIAL_FAILED,
    ];

    protected $table = 'batch_claim';
    protected $primaryKey = 'batch_claim_id';

    protected $fillable = [
        'customer_id',
        'agent_id',
        'batch_status',
        'total_count',
        'completed_count',
        'notes',
    ];

    protected $casts = [
        'total_count' => 'integer',
        'completed_count' => 'integer',
    ];

    protected $appends = ['batch_status_label'];

    public function getBatchStatusLabelAttribute(): string
    {
        return match ($this->batch_status) {
            self::STATUS_DRAFT => '임시저장',
            self::STATUS_PENDING => '접수 대기',
            self::STATUS_PROCESSING => '처리중',
            self::STATUS_COMPLETED => '전체 완료',
            self::STATUS_PARTIAL_FAILED => '일부 실패',
            default => $this->batch_status ?? '알 수 없음',
        };
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    public function claims()
    {
        return $this->hasMany(InsuranceClaim::class, 'batch_claim_id', 'batch_claim_id');
    }

    /**
     * 하위 claim 상태에 따라 배치 상태 자동 갱신
     */
    public function refreshBatchStatus(): void
    {
        $claims = $this->claims()->get();
        $total = $claims->count();

        if ($total === 0) {
            return;
        }

        $completedCount = $claims->whereIn('claim_status', [
            InsuranceClaim::STATUS_APPROVED,
            InsuranceClaim::STATUS_PAID,
        ])->count();

        $rejectedCount = $claims->where('claim_status', InsuranceClaim::STATUS_REJECTED)->count();
        $processingCount = $claims->where('claim_status', InsuranceClaim::STATUS_PROCESSING)->count();

        $newStatus = $this->batch_status;

        if ($completedCount + $rejectedCount === $total) {
            $newStatus = $rejectedCount > 0 ? self::STATUS_PARTIAL_FAILED : self::STATUS_COMPLETED;
        } elseif ($processingCount > 0 || $completedCount > 0) {
            $newStatus = self::STATUS_PROCESSING;
        }

        $this->update([
            'batch_status' => $newStatus,
            'completed_count' => $completedCount,
        ]);
    }
}
