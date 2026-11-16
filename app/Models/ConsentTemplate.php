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
    const TYPE_HEALTH_CHECKUP = 'health_checkup';
    const TYPE_MEDICAL_INFO = 'medical_info';

    const TYPE_UNCLAIMED_SENSITIVE = 'unclaimed_sensitive';
    const TYPE_UNCLAIMED_PERSONAL = 'unclaimed_personal';
    const TYPE_UNCLAIMED_THIRD_PARTY = 'unclaimed_third_party';
    const TYPE_UNCLAIMED_TERMS = 'unclaimed_terms';
    const TYPE_UNCLAIMED_MEDICAL_RECORD = 'unclaimed_medical_record';

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
