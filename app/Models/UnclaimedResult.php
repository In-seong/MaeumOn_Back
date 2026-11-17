<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnclaimedResult extends Model
{
    protected $table = 'unclaimed_result';
    protected $primaryKey = 'result_id';

    protected $fillable = [
        'session_id',
        'medical_record_id',
        'insurance_id',
        'visit_date',
        'hospital_name',
        'diagnosis',
        'treatment_type',
        'total_cost',
        'estimated_claim_amount',
        'matched_insurance_product',
        'matched_coverage',
        'claim_status',
        'claim_id',
        'notes',
    ];

    protected $casts = [
        'visit_date' => 'date:Y-m-d',
        'total_cost' => 'decimal:2',
        'estimated_claim_amount' => 'decimal:2',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(UnclaimedSession::class, 'session_id', 'session_id');
    }
}
