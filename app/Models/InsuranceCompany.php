<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsuranceCompany extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'fax_number',
        'logo_path',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * 보험사의 청구서 양식 템플릿들
     */
    public function claimFormTemplates(): HasMany
    {
        return $this->hasMany(ClaimFormTemplate::class);
    }

    /**
     * 활성화된 양식 템플릿들만
     */
    public function activeTemplates(): HasMany
    {
        return $this->hasMany(ClaimFormTemplate::class)->where('is_active', true);
    }

    /**
     * 활성화된 보험사만 조회하는 스코프
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
