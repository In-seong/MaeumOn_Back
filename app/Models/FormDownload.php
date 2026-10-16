<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormDownload extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_name',
        'category',
        'claim_form_path',
        'claim_form_name',
        'dental_confirm_path',
        'dental_confirm_name',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];
}
