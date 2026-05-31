<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HospitalReservation extends Model
{
    protected $table = 'hospital_reservation';
    protected $primaryKey = 'reservation_id';

    protected $fillable = [
        'hospital_id',
        'center_id',
        'reservation_type',
        'patient_name',
        'patient_phone',
        'reservation_date',
        'reservation_time',
        'memo',
        'status',
    ];

    protected $casts = [
        'reservation_date' => 'date:Y-m-d',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(PartnerHospital::class, 'hospital_id', 'hospital_id');
    }

    public function healthCenter(): BelongsTo
    {
        return $this->belongsTo(HealthCenter::class, 'center_id', 'center_id');
    }
}
