<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class FormPage extends Model
{
    protected $table = 'form_page';
    protected $primaryKey = 'form_page_id';

    protected $fillable = [
        'claim_form_id',
        'page_number',
        'page_title',
        'page_description',
        'page_image_path',
        'image_width',
        'image_height',
    ];

    protected $casts = [
        'page_number' => 'integer',
        'image_width' => 'integer',
        'image_height' => 'integer',
    ];

    protected $appends = ['page_image_url'];

    public function claimForm()
    {
        return $this->belongsTo(ClaimForm::class, 'claim_form_id', 'claim_form_id');
    }

    public function formFields()
    {
        return $this->hasMany(FormField::class, 'form_page_id', 'form_page_id')->orderBy('field_order');
    }

    public function getPageImageUrlAttribute(): ?string
    {
        if ($this->page_image_path) {
            $ttl = config('filesystems.s3_private_url_ttl', 60);
            return Storage::disk('s3')->temporaryUrl($this->page_image_path, now()->addMinutes($ttl));
        }
        return null;
    }
}
