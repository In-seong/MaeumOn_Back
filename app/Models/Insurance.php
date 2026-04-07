<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Insurance extends Model
{
    protected $table = 'insurance';
    protected $primaryKey = 'insurance_id';

    protected $fillable = [
        'customer_id',
        'company_id',
        'policy_number',
        'insurance_type',
        'product_name',
        'coverage_amount',
        'premium_amount',
        'payment_period',
        'subscription_date',
        'expiration_date',
        'is_active',
        // CODEF 추가 컬럼
        'contract_type',
        'contractor_name',
        'contract_status',
        'company_phone',
        'company_homepage',
        'payment_cycle',
        'start_date',
        'end_date',
        'insured_person',
        'res_number',
        'contract_date_of',
        'car_name',
        'car_number',
        'codef_synced',
        'synced_at',
    ];

    protected $casts = [
        'coverage_amount' => 'decimal:2',
        'premium_amount' => 'decimal:2',
        'subscription_date' => 'date',
        'expiration_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'contract_date_of' => 'date',
        'is_active' => 'boolean',
        'codef_synced' => 'boolean',
        'codef_raw_data' => 'json',
        'synced_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function company()
    {
        return $this->belongsTo(InsuranceCompany::class, 'company_id', 'company_id');
    }

    /**
     * Frontend 호환성을 위한 alias (insurance_company 키로도 접근 가능)
     */
    public function insuranceCompany()
    {
        return $this->belongsTo(InsuranceCompany::class, 'company_id', 'company_id');
    }

    public function coverages()
    {
        return $this->hasMany(InsuranceCoverage::class, 'insurance_id', 'insurance_id');
    }

    public function paymentHistories()
    {
        return $this->hasMany(InsurancePaymentHistory::class, 'insurance_id', 'insurance_id');
    }
}
