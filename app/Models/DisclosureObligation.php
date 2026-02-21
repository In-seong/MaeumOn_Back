<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisclosureObligation extends Model
{
    protected $table = 'disclosure_obligation';
    protected $primaryKey = 'disclosure_id';

    protected $fillable = [
        'customer_id',
        'medical_record_id',
        'disease_name',
        'diagnosis_date',
        'tracking_start_date',
        'tracking_end_date',
        'is_disclosed',
        'disclosure_date',
        'notes',
    ];

    protected $casts = [
        'diagnosis_date' => 'date',
        'tracking_start_date' => 'date',
        'tracking_end_date' => 'date',
        'is_disclosed' => 'boolean',
        'disclosure_date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class, 'medical_record_id', 'record_id');
    }
}
