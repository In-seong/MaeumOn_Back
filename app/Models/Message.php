<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $table = 'message';
    protected $primaryKey = 'message_id';
    public $timestamps = false;

    protected $fillable = [
        'receiver_id',
        'receiver_type',
        'sender_id',
        'sender_type',
        'message_type',
        'title',
        'content',
        'image_url',
        'send_method',
        'send_status',
        'scheduled_at',
        'sent_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
