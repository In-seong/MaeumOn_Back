<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormField extends Model
{
    protected $table = 'form_field';
    protected $primaryKey = 'form_field_id';

    protected $fillable = [
        'claim_form_id',
        'form_page_id',
        'field_name',
        'standard_field_code',
        'field_label',
        'field_type',
        'field_order',
        'is_required',
        'field_options',
        'validation_rules',
        'x_position',
        'y_position',
        'width',
        'height',
        'font_size',
        'font_color',
        'placeholder',
        'default_value',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'field_options' => 'array',
        'validation_rules' => 'array',
        'field_order' => 'integer',
        'x_position' => 'integer',
        'y_position' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'font_size' => 'integer',
    ];

    public function claimForm()
    {
        return $this->belongsTo(ClaimForm::class, 'claim_form_id', 'claim_form_id');
    }

    public function formPage()
    {
        return $this->belongsTo(FormPage::class, 'form_page_id', 'form_page_id');
    }

    public function claimFieldValues()
    {
        return $this->hasMany(ClaimFieldValue::class, 'form_field_id', 'form_field_id');
    }
}
