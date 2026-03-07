<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    protected $table = 'agent_reminder';
    protected $primaryKey = 'reminder_id';

    protected $fillable = [
        'event_id',
        'agent_id',
        'remind_before_days',
        'is_sent',
        'sent_at',
    ];

    protected $casts = [
        'is_sent' => 'boolean',
        'sent_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class, 'event_id', 'event_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }
}
