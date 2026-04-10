<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedicalRecord extends Model
{
    protected $table = 'medical_record';
    protected $primaryKey = 'record_id';

    const SOURCE_MANUAL = 'manual';
    const SOURCE_CODEF_HIRA = 'codef_hira';

    protected $fillable = [
        'customer_id',
        'treatment_date',
        'hospital_name',
        'department',
        'diagnosis_code',
        'diagnosis_name',
        'treatment_type',
        'medical_cost',
        'prescription',
        'is_important',
        // HIRA 추가 필드
        'visit_days',
        'total_amount',
        'public_charge',
        'deductible_amt',
        'hospital_code',
        'detail_treat_list_json',
        'prescribe_drug_list_json',
        // 동기화 메타
        'codef_synced',
        'synced_at',
        'source',
        'codef_raw_data',
    ];

    protected $casts = [
        'treatment_date' => 'date',
        'medical_cost' => 'decimal:2',
        'is_important' => 'boolean',
        'visit_days' => 'integer',
        'total_amount' => 'decimal:2',
        'public_charge' => 'decimal:2',
        'deductible_amt' => 'decimal:2',
        'codef_synced' => 'boolean',
        'synced_at' => 'datetime',
    ];

    protected $appends = ['detail_treat_list', 'prescribe_drug_list'];

    /**
     * 세부진료 목록 디코드
     */
    public function getDetailTreatListAttribute(): array
    {
        return $this->detail_treat_list_json ? (json_decode($this->detail_treat_list_json, true) ?? []) : [];
    }

    /**
     * 처방약 목록 디코드
     */
    public function getPrescribeDrugListAttribute(): array
    {
        return $this->prescribe_drug_list_json ? (json_decode($this->prescribe_drug_list_json, true) ?? []) : [];
    }

    public function scopeForCustomer($query, string $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeFromHira($query)
    {
        return $query->where('source', self::SOURCE_CODEF_HIRA);
    }

    public function scopeManual($query)
    {
        return $query->where('source', self::SOURCE_MANUAL);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function disclosureObligations(): HasMany
    {
        return $this->hasMany(DisclosureObligation::class, 'medical_record_id', 'record_id');
    }
}
