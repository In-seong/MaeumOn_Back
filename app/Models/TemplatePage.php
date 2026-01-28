<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class TemplatePage extends Model
{
    protected $fillable = [
        'claim_form_template_id',
        'page_number',
        'page_image_path',
        'image_width',
        'image_height',
    ];

    protected $casts = [
        'page_number' => 'integer',
        'image_width' => 'integer',
        'image_height' => 'integer',
    ];

    protected $appends = ['page_image_url'];

    /**
     * 페이지 이미지 URL
     */
    public function getPageImageUrlAttribute(): ?string
    {
        if (!$this->page_image_path) {
            return null;
        }

        return Storage::disk('public')->url($this->page_image_path);
    }

    /**
     * 양식 템플릿
     */
    public function claimFormTemplate(): BelongsTo
    {
        return $this->belongsTo(ClaimFormTemplate::class);
    }

    /**
     * 템플릿 필드들
     */
    public function templateFields(): HasMany
    {
        return $this->hasMany(TemplateField::class)->orderBy('sort_order');
    }
}
