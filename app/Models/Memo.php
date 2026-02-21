<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Memo extends Model
{
    protected $table = 'memo';
    protected $primaryKey = 'memo_id';

    protected $fillable = [
        'customer_id',
        'author_id',
        'author_type',
        'title',
        'content',
        'memo_date',
    ];

    protected $casts = [
        'memo_date' => 'date:Y-m-d',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }
}
