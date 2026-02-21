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
        'title',
        'content',
        'customer_name',
        'customer_phone',
        'birth_date',
        'region',
        'status',
        'answer',
        'answered_at',
        'satisfaction_rating',
        'created_by_id',
        'updated_by_id',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'answered_at' => 'datetime',
        'satisfaction_rating' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }
}
