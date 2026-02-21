<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAssignment extends Model
{
    protected $table = 'customer_assignment';
    protected $primaryKey = 'assignment_id';

    protected $fillable = [
        'customer_id',
        'agent_id',
        'admin_id',
        'assignment_type',
        'assignment_reason',
        'customer_info',
        'status',
        'processed_at',
        'is_converted',
        'contract_date',
        'contract_amount',
        'created_by_id',
        'updated_by_id',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'is_converted' => 'boolean',
        'contract_date' => 'date',
        'contract_amount' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }
}
