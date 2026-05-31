<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerHospital extends Model
{
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
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function reservations(): HasMany
    {
        return $this->hasMany(HospitalReservation::class, 'hospital_id', 'hospital_id');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(HospitalAccount::class, 'hospital_id', 'hospital_id');
    }
}
