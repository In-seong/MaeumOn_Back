<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Credit4uAccount extends Model
{
    protected $table = 'credit4u_account';
    protected $primaryKey = 'credit4u_account_id';

    protected $fillable = [
        'customer_id',
        'credit4u_login_id',
        'registration_status',
        'consent_template_id',
        'last_synced_at',
        'consent_agreed_at',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
        'consent_agreed_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function consentTemplate()
    {
        return $this->belongsTo(ConsentTemplate::class, 'consent_template_id', 'consent_template_id');
    }
}
