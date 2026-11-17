<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class UnclaimedSession extends Model
{
    protected $table = 'unclaimed_session';
    protected $primaryKey = 'session_id';

    protected $fillable = [
        'agent_id',
        'customer_id',
        'customer_name',
        'customer_phone',
        'customer_birth_date',
        'customer_resident_number',
        'customer_address',
        'customer_detailed_address',
        'customer_gender',
        'customer_telecom',
        'status',
        'id_card_path',
        'signature_path',
        'total_unclaimed_amount',
        'analyzed_at',
        'notes',
    ];

    protected $casts = [
        'customer_birth_date' => 'date:Y-m-d',
        'analyzed_at' => 'datetime',
        'total_unclaimed_amount' => 'decimal:2',
    ];

    protected $hidden = [
        'customer_resident_number',
    ];

    public function getCustomerResidentNumberAttribute(?string $value): ?string
    {
        if ($value === null || $value === '') return $value;

        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return $value;
        }
    }

    public function setCustomerResidentNumberAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['customer_resident_number'] = $value;
            return;
        }

        try {
            Crypt::decryptString($value);
            $this->attributes['customer_resident_number'] = $value;
            return;
        } catch (\Exception $e) {
            // 평문 → 암호화
        }

        $this->attributes['customer_resident_number'] = Crypt::encryptString($value);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(UnclaimedResult::class, 'session_id', 'session_id');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(UnclaimedConsent::class, 'session_id', 'session_id');
    }
}
