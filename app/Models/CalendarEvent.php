<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarEvent extends Model
{
    protected $table = 'agent_calendar_event';
    protected $primaryKey = 'event_id';

    // event_type 상수
    const TYPE_MANUAL = 'manual';
    const TYPE_BIRTHDAY = 'birthday';
    const TYPE_CONTRACT_EXPIRY = 'contract_expiry';
    const TYPE_INSURANCE_EXPIRY = 'insurance_expiry';

    // source 상수
    const SOURCE_MANUAL = 'manual';
    const SOURCE_SYSTEM = 'system';

    protected $fillable = [
        'agent_id',
        'customer_id',
        'contract_id',
        'event_type',
        'title',
        'memo',
        'event_date',
        'start_time',
        'end_time',
        'is_all_day',
        'is_recurring',
        'is_completed',
        'source',
    ];

    protected $casts = [
        'event_date' => 'date',
        'is_all_day' => 'boolean',
        'is_recurring' => 'boolean',
        'is_completed' => 'boolean',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id', 'contract_id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class, 'event_id', 'event_id');
    }
}
