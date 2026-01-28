<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsuranceClaim extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'claim_form_template_id',
        'status',
        'generated_image_path',
        'generated_pdf_path',
        'fax_sent_at',
        'fax_status',
        'notes',
    ];

    protected $casts = [
        'fax_sent_at' => 'datetime',
    ];

    /**
     * 청구한 고객
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 사용한 양식 템플릿
     */
    public function claimFormTemplate(): BelongsTo
    {
        return $this->belongsTo(ClaimFormTemplate::class);
    }

    /**
     * 청구서에 입력된 필드 값들
     */
    public function fieldValues(): HasMany
    {
        return $this->hasMany(ClaimFieldValue::class);
    }

    /**
     * 상태 한글 라벨
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => '대기중',
            'processing' => '처리중',
            'completed' => '완료',
            'rejected' => '거절',
            default => $this->status,
        };
    }

    /**
     * 팩스 상태 한글 라벨
     */
    public function getFaxStatusLabelAttribute(): ?string
    {
        if (!$this->fax_status) {
            return null;
        }

        return match ($this->fax_status) {
            'pending' => '발송 대기',
            'sent' => '발송 완료',
            'failed' => '발송 실패',
            default => $this->fax_status,
        };
    }

    /**
     * 생성된 이미지 URL
     */
    public function getGeneratedImageUrlAttribute(): ?string
    {
        if (!$this->generated_image_path) {
            return null;
        }
        return asset('storage/' . $this->generated_image_path);
    }

    /**
     * 생성된 PDF URL
     */
    public function getGeneratedPdfUrlAttribute(): ?string
    {
        if (!$this->generated_pdf_path) {
            return null;
        }
        return asset('storage/' . $this->generated_pdf_path);
    }

    /**
     * 상태별 스코프
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
