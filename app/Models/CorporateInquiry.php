<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorporateInquiry extends Model
{
    protected $table = 'corporate_inquiries';

    protected $fillable = [
        'company_name',
        'address',
        'ceo_name',
        'phone',
        'annual_revenue',
        'industry',
        'consultation_field',
        'privacy_agreed',
        'status',
        'agent_id',
        'assigned_at',
        'notes',
    ];

    protected $casts = [
        'privacy_agreed' => 'boolean',
        'assigned_at' => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }
}
