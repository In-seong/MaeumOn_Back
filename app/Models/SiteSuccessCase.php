<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteSuccessCase extends Model
{
    protected $table = 'site_success_case';

    protected $fillable = [
        'title', 'author_id', 'author_name', 'keywords', 'summary', 'detail', 'image_url', 'is_deleted', 'deleted_at',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'deleted_at' => 'datetime',
        'author_id' => 'integer',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(SiteAdmin::class, 'author_id');
    }

    public function toApiFormat(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'keywords' => $this->keywords ? explode(',', $this->keywords) : [],
            'summary' => $this->summary ?? '',
            'detail' => $this->detail ?? '',
            'imageUrl' => $this->image_url ?? '',
            'authorName' => $this->author_name,
            'createdAt' => $this->created_at->format('Y-m-d\TH:i:s+09:00'),
        ];
    }
}
