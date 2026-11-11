<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceTask extends Model
{
    protected $table = 'maintenance_tasks';

    protected $fillable = [
        'title',
        'description',
        'priority',
        'status',
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
