<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class HealthCenterImage extends Model
{
    protected $table = 'health_center_image';
    protected $primaryKey = 'image_id';

    public $timestamps = false;

    protected $fillable = [
        'center_id',
        'image_path',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_path) return null;
        return Storage::disk('s3')->temporaryUrl($this->image_path, now()->addHours(24));
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(HealthCenter::class, 'center_id', 'center_id');
    }
}
