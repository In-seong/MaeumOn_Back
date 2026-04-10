<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthExternalAccount extends Model
{
    protected $table = 'health_external_account';
    protected $primaryKey = 'health_external_account_id';

    protected $fillable = [
        'customer_id',
        'checkup_consent_at',
        'medical_info_consent_at',
        'health_in_linked',
        'health_in_linked_at',
        'last_checkup_sync_at',
        'last_medical_info_sync_at',
        'last_examination_sync_at',
        'last_health_age_sync_at',
        'last_prediction_sync_at',
        'medical_info_range_start',
        'medical_info_range_end',
        'is_active',
    ];

    protected $casts = [
        'checkup_consent_at' => 'datetime',
        'medical_info_consent_at' => 'datetime',
        'health_in_linked' => 'boolean',
        'health_in_linked_at' => 'datetime',
        'last_checkup_sync_at' => 'datetime',
        'last_medical_info_sync_at' => 'datetime',
        'last_examination_sync_at' => 'datetime',
        'last_health_age_sync_at' => 'datetime',
        'last_prediction_sync_at' => 'datetime',
        'medical_info_range_start' => 'date',
        'medical_info_range_end' => 'date',
        'is_active' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    /**
     * NHIS 건강검진 동의 여부
     */
    public function hasCheckupConsent(): bool
    {
        return $this->checkup_consent_at !== null;
    }

    /**
     * HIRA 내진료정보열람 동의 여부
     */
    public function hasMedicalInfoConsent(): bool
    {
        return $this->medical_info_consent_at !== null;
    }
}
