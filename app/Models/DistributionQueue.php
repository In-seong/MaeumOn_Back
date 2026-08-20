<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributionQueue extends Model
{
    protected $table = 'distribution_queue';
    protected $primaryKey = 'queue_id';

    protected $fillable = [
        'customer_id',
        'branch_id',
        'status',
        'scheduled_at',
        'assigned_agent_id',
        'assigned_at',
        'viewed_at',
        'timeout_count',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'assigned_at' => 'datetime',
        'viewed_at' => 'datetime',
        'timeout_count' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'assigned_agent_id', 'agent_id');
    }
}
