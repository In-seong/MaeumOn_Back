<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class HospitalImage extends Model
{
    protected $table = 'hospital_image';
    protected $primaryKey = 'image_id';

    public $timestamps = false;

    protected $fillable = [
        'hospital_id',
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

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(PartnerHospital::class, 'hospital_id', 'hospital_id');
    }
}
