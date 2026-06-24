<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class InsuranceCompany extends Model
{
    protected $table = 'insurance_company';
    protected $primaryKey = 'company_id';

    protected $fillable = [
        'company_name',
        'category',
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

    protected $appends = ['logo_url'];

    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_path) {
            return null;
        }

        return Storage::disk('s3')->temporaryUrl($this->logo_path, now()->addHours(24));
    }

    public function claimForms()
    {
        return $this->hasMany(ClaimForm::class, 'company_id', 'company_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
