<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerStatus extends Model
{
    protected $table = 'customer_status';
    protected $primaryKey = 'status_id';

    protected $fillable = [
        'customer_id',
        'agent_id',
        'status_type',
        'status_value',
        'status_description',
        'status_date',
        'is_important',
        'created_by_id',
        'updated_by_id',
    ];

    protected $casts = [
        'status_date' => 'date',
        'is_important' => 'boolean',
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
