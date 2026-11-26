<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CustomerAssignment extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
    protected $table = 'customer_assignment';
    protected $primaryKey = 'assignment_id';

    protected $fillable = [
        'customer_id',
        'agent_id',
        'admin_id',
        'assignment_type',
        'assignment_date',
        'notes',
        'confirmed_at',
        'created_by_id',
        'updated_by_id',
    ];

    protected $casts = [
        'assignment_date' => 'date',
        'confirmed_at' => 'datetime',
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
