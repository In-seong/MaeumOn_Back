<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consultation extends Model
{
    protected $table = 'consultation';
    protected $primaryKey = 'consultation_id';

    protected $fillable = [
        'customer_id',
        'assignee_id',
        'assignee_type',
        'consultation_type',
        'consultation_date',
        'consultation_content',
        'consultation_answer',
        'consultation_status',
        'customer_name',
        'customer_phone',
        'created_by_id',
        'updated_by_id',
    ];

    protected $casts = [
        'consultation_date' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }
}
