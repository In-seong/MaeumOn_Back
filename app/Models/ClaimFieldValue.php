<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimFieldValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'insurance_claim_id',
        'template_field_id',
        'field_value',
    ];

    /**
     * 소속 청구
     */
    public function insuranceClaim(): BelongsTo
    {
        return $this->belongsTo(InsuranceClaim::class);
    }

    /**
     * 연결된 필드 정의
     */
    public function templateField(): BelongsTo
    {
        return $this->belongsTo(TemplateField::class);
    }
}
