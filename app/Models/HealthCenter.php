<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'schedule_config',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'schedule_config' => 'array',
    ];

    public function reservations(): HasMany
    {
        return $this->hasMany(HospitalReservation::class, 'center_id', 'center_id');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(HospitalAccount::class, 'center_id', 'center_id');
    }
}
