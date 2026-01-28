<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class ClaimFormTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'insurance_company_id',
        'name',
        'description',
        'template_image_path',
        'image_width',
        'image_height',
        'is_active',
    ];

    protected $casts = [
        'image_width' => 'integer',
        'image_height' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $appends = ['template_image_url', 'total_pages'];

    /**
     * 소속 보험사
     */
    public function insuranceCompany(): BelongsTo
    {
        return $this->belongsTo(InsuranceCompany::class);
    }

    /**
     * 양식의 페이지들
     */
    public function templatePages(): HasMany
    {
        return $this->hasMany(TemplatePage::class)->orderBy('page_number');
    }

    /**
     * 양식의 필드들 (직접 연결 - 하위 호환성)
     */
    public function templateFields(): HasMany
    {
        return $this->hasMany(TemplateField::class)->orderBy('sort_order');
    }

    /**
     * 이 양식으로 작성된 청구들
     */
    public function insuranceClaims(): HasMany
    {
        return $this->hasMany(InsuranceClaim::class);
    }

    /**
     * 활성화된 템플릿만 조회하는 스코프
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * 템플릿 이미지 URL 접근자 (첫 번째 페이지 이미지, 하위 호환성)
     */
    public function getTemplateImageUrlAttribute(): ?string
    {
        // 새 구조: 첫 번째 페이지 이미지
        $firstPage = $this->templatePages()->first();
        if ($firstPage && $firstPage->page_image_path) {
            return asset('storage/' . $firstPage->page_image_path);
        }

        // 구 구조: 직접 저장된 이미지 (마이그레이션 전 데이터)
        if ($this->template_image_path) {
            return asset('storage/' . $this->template_image_path);
        }

        return null;
    }

    /**
     * 전체 페이지 수
     */
    public function getTotalPagesAttribute(): int
    {
        return $this->templatePages()->count() ?: ($this->template_image_path ? 1 : 0);
    }
}
