<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsurancePaymentHistory extends Model
{
    protected $table = 'insurance_payment_history';
    protected $primaryKey = 'payment_id';

    protected $fillable = [
        'customer_id',
        'insurance_id',
        'company_name',
        'policy_number',
        'insurance_name',
        'res_number',
        'occur_date_time',
        'total_amount',
        'payment_type',
        'reason',
        'paid_amount',
        'payment_date',
        'judge_result',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function insurance()
    {
        return $this->belongsTo(Insurance::class, 'insurance_id', 'insurance_id');
    }
}
