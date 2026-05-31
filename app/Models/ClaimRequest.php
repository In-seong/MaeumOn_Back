<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClaimRequest extends Model
{
    protected $table = 'claim_request';
    protected $primaryKey = 'request_id';

    protected $fillable = [
        'name',
        'phone',
        'memo',
        'status',
        'assigned_agent_id',
        'linked_claim_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function files(): HasMany
    {
        return $this->hasMany(ClaimRequestFile::class, 'request_id', 'request_id');
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'assigned_agent_id', 'agent_id');
    }

    public function linkedClaim(): BelongsTo
    {
        return $this->belongsTo(InsuranceClaim::class, 'linked_claim_id', 'claim_id');
    }
}
