<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsuranceStatistics extends Model
{
    protected $table = 'insurance_statistics';
    protected $primaryKey = 'stat_id';

    protected $fillable = [
        'customer_id',
        'stat_type',
        'coverage_name',
        'self_coverage_amt',
        'avg_group_coverage_amt',
        'self_reg_yn',
        'avg_group_reg_rate',
        'synced_at',
    ];

    protected $casts = [
        'self_coverage_amt' => 'decimal:2',
        'avg_group_coverage_amt' => 'decimal:2',
        'synced_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }
}
