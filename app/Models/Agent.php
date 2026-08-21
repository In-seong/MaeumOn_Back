<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agent extends Model
{
    use SoftDeletes;
    protected $table = 'agent';
    protected $primaryKey = 'agent_id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'agent_id',
        'account_id',
        'name',
        'employee_number',
        'phone',
        'email',
        'office_location',
        'specialization',
        'hire_date',
        'resignation_date',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'agent_branch', 'agent_id', 'branch_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id', 'account_id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'agent_id', 'agent_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'agent_id', 'agent_id');
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class, 'assignee_id', 'agent_id');
    }

    public function memos(): HasMany
    {
        return $this->hasMany(Memo::class, 'author_id', 'agent_id');
    }

    public function satisfactionSurveys(): HasMany
    {
        return $this->hasMany(SatisfactionSurvey::class, 'agent_id', 'agent_id');
    }

    public function customerAssignments(): HasMany
    {
        return $this->hasMany(CustomerAssignment::class, 'agent_id', 'agent_id');
    }

    public function performances(): HasMany
    {
        return $this->hasMany(Performance::class, 'agent_id', 'agent_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'receiver_id', 'agent_id');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id', 'agent_id');
    }

    public function insuranceClaims(): HasMany
    {
        return $this->hasMany(InsuranceClaim::class, 'agent_id', 'agent_id');
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class, 'agent_id', 'agent_id');
    }
}
