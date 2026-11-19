<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PopupNotice extends Model
{
    protected $table = 'popup_notice';
    protected $primaryKey = 'popup_notice_id';

    protected $fillable = [
        'title',
        'image_path',
        'link_url',
        'start_date',
        'end_date',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_path) return null;
        return Storage::disk('s3')->temporaryUrl($this->image_path, now()->addHours(24));
    }

    public function scopeActive(Builder $query): Builder
    {
        $today = now()->toDateString();
        return $query->where('is_active', true)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today);
    }
}
