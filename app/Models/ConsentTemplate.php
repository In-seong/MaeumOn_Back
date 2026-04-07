<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsentTemplate extends Model
{
    protected $table = 'consent_template';
    protected $primaryKey = 'consent_template_id';

    const TYPE_UNIQUE_ID = 'unique_id';
    const TYPE_SENSITIVE = 'sensitive';
    const TYPE_CREDIT = 'credit';
    const TYPE_CREDIT4U = 'credit4u';

    protected $fillable = [
        'consent_type',
        'title',
        'content',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
