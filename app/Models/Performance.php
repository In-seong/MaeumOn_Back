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
        'db_processed_count',
        'contract_count',
        'contract_amount',
        'conversion_rate',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'db_assigned_count' => 'integer',
        'db_processed_count' => 'integer',
        'contract_count' => 'integer',
        'contract_amount' => 'decimal:2',
        'conversion_rate' => 'decimal:2',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }
}
