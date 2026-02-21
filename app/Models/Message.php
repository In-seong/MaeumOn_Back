<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $table = 'message';
    protected $primaryKey = 'message_id';
    protected $fillable = [
        'receiver_id',
        'sender_id',
        'sender_type',
        'phone_number',
        'message_type',
        'message_content',
        'image_url',
        'send_status',
        'scheduled_at',
        'sent_at',
        'error_message',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];
}
