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
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];
}
