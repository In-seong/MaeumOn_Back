<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsuranceCoverage extends Model
{
    protected $table = 'insurance_coverage';
    protected $primaryKey = 'coverage_id';

    protected $fillable = [
        'insurance_id',
        'insured_person',
        'coverage_name',
        'coverage_amount',
        'coverage_status',
        'agreement_type',
        'coverage_type',
        'object_info',
        'zip_code',
    ];

    protected $casts = [
        'coverage_amount' => 'decimal:2',
    ];

    public function insurance()
    {
        return $this->belongsTo(Insurance::class, 'insurance_id', 'insurance_id');
    }
}
