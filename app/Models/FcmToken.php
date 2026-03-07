<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FcmToken extends Model
{
    protected $table = 'fcm_token';
    protected $primaryKey = 'id';

    protected $fillable = [
        'user_type',
        'user_id',
        'fcm_token',
        'device_info',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
