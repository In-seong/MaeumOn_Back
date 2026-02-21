<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisclosureObligation extends Model
{
    protected $table = 'disclosure_obligation';
    protected $primaryKey = 'obligation_id';

    protected $fillable = [
        'customer_id',
        'medical_record_id',
        'obligation_start_date',
        'obligation_end_date',
        'obligation_status',
        'is_notified',
        'notification_date',
        'notes',
    ];

    protected $casts = [
        'obligation_start_date' => 'date',
        'obligation_end_date' => 'date',
        'is_notified' => 'boolean',
        'notification_date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class, 'medical_record_id', 'record_id');
    }
}
