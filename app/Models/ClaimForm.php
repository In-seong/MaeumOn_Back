<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ClaimForm extends Model
{
    protected $table = 'claim_form';
    protected $primaryKey = 'claim_form_id';

    protected $fillable = [
        'company_id',
        'form_name',
        'form_description',
        'form_version',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = ['template_image_url', 'total_pages'];

    public function insuranceCompany()
    {
        return $this->belongsTo(InsuranceCompany::class, 'company_id', 'company_id');
    }

    public function formPages()
    {
        return $this->hasMany(FormPage::class, 'claim_form_id', 'claim_form_id')->orderBy('page_number');
    }

    public function formFields()
    {
        return $this->hasMany(FormField::class, 'claim_form_id', 'claim_form_id')->where('is_hidden', false)->orderBy('field_order');
    }

    public function insuranceClaims()
    {
        return $this->hasMany(InsuranceClaim::class, 'claim_form_id', 'claim_form_id');
    }

    public function getTemplateImageUrlAttribute(): ?string
    {
        $firstPage = $this->formPages()->first();
        if ($firstPage && $firstPage->page_image_path) {
            $ttl = config('filesystems.s3_private_url_ttl', 60);
            return Storage::disk('s3')->temporaryUrl($firstPage->page_image_path, now()->addMinutes($ttl));
        }
        return null;
    }

    public function getTotalPagesAttribute(): int
    {
        return $this->formPages()->count();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
