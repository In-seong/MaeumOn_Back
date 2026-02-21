<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedicalRecord extends Model
{
    protected $table = 'medical_record';
    protected $primaryKey = 'record_id';

    protected $fillable = [
        'customer_id',
        'treatment_date',
        'hospital_name',
        'diagnosis_code',
        'diagnosis_name',
        'treatment_type',
        'medical_cost',
        'prescription',
        'is_important',
    ];

    protected $casts = [
        'treatment_date' => 'date',
        'medical_cost' => 'decimal:2',
        'is_important' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function disclosureObligations(): HasMany
    {
        return $this->hasMany(DisclosureObligation::class, 'medical_record_id', 'record_id');
    }
}
