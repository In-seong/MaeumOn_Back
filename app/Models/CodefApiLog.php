<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodefApiLog extends Model
{
    protected $table = 'codef_api_logs';
    protected $primaryKey = 'log_id';

    protected $fillable = [
        'agent_id',
        'customer_id',
        'api_type',
        'api_action',
        'status',
        'result_count',
        'error_message',
        'billed',
        'billed_at',
        'billing_month',
    ];

    protected $casts = [
        'billed' => 'boolean',
        'billed_at' => 'datetime',
        'result_count' => 'integer',
    ];

    public const TYPE_INSURANCE = 'insurance';
    public const TYPE_MEDICAL = 'medical';
    public const TYPE_CHECKUP = 'checkup';
    public const TYPE_HEALTH_AGE = 'health_age';

    public const ACTION_FETCH = 'fetch';
    public const ACTION_CONFIRM = 'confirm';

    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_TWO_WAY = 'two_way';

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }
}
