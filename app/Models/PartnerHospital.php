<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use App\Models\Traits\HasScheduleConfig;

class PartnerHospital extends Model
{
    use HasScheduleConfig;

    protected $table = 'partner_hospital';
    protected $primaryKey = 'hospital_id';

    protected $fillable = [
        'hospital_name',
        'business_number',
        'address',
        'detailed_address',
        'contact_phone',
        'latitude',
        'longitude',
        'business_hours',
        'introduction',
        'specialties',
        'schedule_config',
        'image_path',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'schedule_config' => 'array',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_path) return null;
        return Storage::disk('s3')->temporaryUrl($this->image_path, now()->addHours(24));
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(HospitalReservation::class, 'hospital_id', 'hospital_id');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(HospitalAccount::class, 'hospital_id', 'hospital_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(HospitalImage::class, 'hospital_id', 'hospital_id')->orderBy('sort_order');
    }
}
