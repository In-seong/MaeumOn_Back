<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use App\Models\Traits\HasScheduleConfig;

class HealthCenter extends Model
{
    use HasScheduleConfig;

    protected $table = 'health_center';
    protected $primaryKey = 'center_id';

    protected $fillable = [
        'center_name',
        'address',
        'detailed_address',
        'latitude',
        'longitude',
        'contact_phone',
        'business_hours',
        'introduction',
        'thumbnail_path',
        'schedule_config',
        'reservation_enabled',
        'is_active',
        'is_deleted',
    ];

    protected $casts = [
        'reservation_enabled' => 'boolean',
        'is_active' => 'boolean',
        'is_deleted' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'schedule_config' => 'array',
    ];

    protected $appends = ['thumbnail_url'];

    public function getThumbnailUrlAttribute(): ?string
    {
        if (!$this->thumbnail_path) {
            return null;
        }

        return Storage::disk('s3')->temporaryUrl($this->thumbnail_path, now()->addHours(24));
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(HospitalReservation::class, 'center_id', 'center_id');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(HospitalAccount::class, 'center_id', 'center_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(HealthCenterImage::class, 'center_id', 'center_id')->orderBy('sort_order');
    }
}
