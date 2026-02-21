<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SatisfactionSurvey extends Model
{
    protected $table = 'satisfaction_survey';
    protected $primaryKey = 'survey_id';

    protected $fillable = [
        'agent_id',
        'customer_id',
        'survey_title',
        'survey_content',
        'rating',
        'feedback',
        'survey_status',
        'sent_at',
        'responded_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'sent_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }
}
