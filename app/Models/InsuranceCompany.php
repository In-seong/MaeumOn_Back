<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsuranceCompany extends Model
{
    protected $table = 'insurance_company';
    protected $primaryKey = 'company_id';

    protected $fillable = [
        'company_name',
        'company_code',
        'business_number',
        'representative_name',
        'address',
        'contact_phone',
        'fax_number',
        'logo_path',
        'website_url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function claimForms()
    {
        return $this->hasMany(ClaimForm::class, 'company_id', 'company_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
