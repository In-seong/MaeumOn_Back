<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceTask extends Model
{
    use SoftDeletes;

    protected $table = 'maintenance_tasks';

    protected $fillable = [
        'title',
        'description',
        'priority',
        'status',
        'status_reason',
        'source',
        'category',
        'target_month',
        'completed_at',
        'images',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'images' => 'array',
    ];
}
