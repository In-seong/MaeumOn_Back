<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClaimFieldValue extends Model
{
    protected $table = 'claim_field_value';
    protected $primaryKey = 'claim_field_value_id';

    protected $fillable = [
        'claim_id',
        'form_field_id',
        'field_value',
    ];

    public function insuranceClaim()
    {
        return $this->belongsTo(InsuranceClaim::class, 'claim_id', 'claim_id');
    }

    public function formField()
    {
        return $this->belongsTo(FormField::class, 'form_field_id', 'form_field_id');
    }
}
