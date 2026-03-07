<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contract extends Model
{
    protected $table = 'contract';
    protected $primaryKey = 'contract_id';

    protected $fillable = [
        'customer_id',
        'agent_id',
        'company_id',
        'contract_number',
        'contract_amount',
        'contract_date',
        'contract_status',
        'customer_name',
        'customer_phone',
        'insurance_product',
        'payment_method',
        'expiration_date',
        'notes',
        'created_by_id',
        'updated_by_id',
    ];

    protected $casts = [
        'contract_date' => 'date',
        'expiration_date' => 'date',
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

    public function insuranceCompany(): BelongsTo
    {
        return $this->belongsTo(InsuranceCompany::class, 'company_id', 'company_id');
    }
}
