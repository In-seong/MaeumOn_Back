<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HospitalAccount extends Model
{
    protected $table = 'hospital_account';
    protected $primaryKey = 'account_id';

    protected $fillable = [
        'hospital_id',
        'center_id',
        'username',
        'password',
        'account_name',
        'is_active',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'is_active' => 'boolean',
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
