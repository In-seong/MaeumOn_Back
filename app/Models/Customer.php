<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $table = 'customer';
    protected $primaryKey = 'customer_id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'customer_id',
        'account_id',
        'agent_id',
        'name',
        'resident_number',
        'gender',
        'birth_date',
        'phone',
        'email',
        'address',
        'detailed_address',
        'job',
        'acquisition_channel',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'birth_date' => 'date',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id', 'account_id');
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    public function insuranceClaims()
    {
        return $this->hasMany(InsuranceClaim::class, 'customer_id', 'customer_id');
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class, 'customer_id', 'customer_id');
    }

    public function memos()
    {
        return $this->hasMany(Memo::class, 'customer_id', 'customer_id');
    }

    public function consultations()
    {
        return $this->hasMany(Consultation::class, 'customer_id', 'customer_id');
    }

    public function disclosureObligations()
    {
        return $this->hasMany(DisclosureObligation::class, 'customer_id', 'customer_id');
    }
}
