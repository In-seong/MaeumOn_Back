<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AwardPlan extends Model
{
    protected $fillable = [
        'form_download_id',
        'title',
        'file_path',
        'file_name',
        'thumbnail_path',
        'target_year',
        'target_month',
        'sort_order',
    ];

    protected $casts = [
        'form_download_id' => 'integer',
        'target_year' => 'integer',
        'target_month' => 'integer',
        'sort_order' => 'integer',
    ];

    protected $appends = ['thumbnail_url', 'file_url'];

    public function formDownload(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\FormDownload::class);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (!$this->thumbnail_path) return null;
        return Storage::disk('s3')->temporaryUrl($this->thumbnail_path, now()->addMinutes(60));
    }

    public function getFileUrlAttribute(): ?string
    {
        if (!$this->file_path) return null;
        return Storage::disk('s3')->temporaryUrl($this->file_path, now()->addMinutes(60));
    }
}
