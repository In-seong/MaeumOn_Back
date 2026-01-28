<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TemplateField extends Model
{
    use HasFactory;

    protected $fillable = [
        'claim_form_template_id',
        'template_page_id',
        'field_name',
        'field_label',
        'field_type',
        'x_position',
        'y_position',
        'width',
        'height',
        'font_size',
        'font_color',
        'is_required',
        'sort_order',
        'placeholder',
        'default_value',
    ];

    protected $casts = [
        'template_page_id' => 'integer',
        'x_position' => 'integer',
        'y_position' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'font_size' => 'integer',
        'is_required' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * 소속 양식 템플릿
     */
    public function claimFormTemplate(): BelongsTo
    {
        return $this->belongsTo(ClaimFormTemplate::class);
    }

    /**
     * 소속 페이지
     */
    public function templatePage(): BelongsTo
    {
        return $this->belongsTo(TemplatePage::class);
    }

    /**
     * 이 필드에 입력된 값들
     */
    public function claimFieldValues(): HasMany
    {
        return $this->hasMany(ClaimFieldValue::class);
    }

    /**
     * 필드 타입 목록
     */
    public static function getFieldTypes(): array
    {
        return [
            'text' => '텍스트',
            'date' => '날짜',
            'number' => '숫자',
            'resident_number' => '주민등록번호',
            'phone' => '전화번호',
            'textarea' => '여러 줄 텍스트',
        ];
    }
}
