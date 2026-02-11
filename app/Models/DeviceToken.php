<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    protected $table = 'device_token';
    protected $primaryKey = 'device_token_id';

    protected $fillable = [
        'account_id',
        'device_uuid',
        'token_hash',
        'device_name',
        'is_active',
        'last_used_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id', 'account_id');
    }
}
