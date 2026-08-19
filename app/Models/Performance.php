<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Performance extends Model
{
    protected $table = 'performance';
    protected $primaryKey = 'performance_id';

    protected $fillable = [
        'agent_id',
        'year',
        'month',
        'db_assigned_count',
        'contract_count',
        'contract_amount',
        'branch_id',
        'consultation_count',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'db_assigned_count' => 'integer',
        'contract_count' => 'integer',
        'contract_amount' => 'decimal:2',
        'consultation_count' => 'integer',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }
}
