<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FcMsgTran extends Model
{
    protected $table = 'fc_msg_tran';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'tr_batchid',
        'tr_serialno',
        'tr_senddate',
        'tr_name',
        'tr_phone',
        'tr_email',
        'tr_sendstat',
        'tr_rsltstat',
        'tr_sendtime',
        'tr_recvtime',
        'tr_duration',
        'tr_pagecnt',
    ];

    protected $casts = [
        'tr_senddate' => 'datetime',
    ];

    public function getKeyName()
    {
        return ['tr_batchid', 'tr_serialno'];
    }

    public function meta(): BelongsTo
    {
        return $this->belongsTo(FcMetaTran::class, 'tr_batchid', 'tr_batchid');
    }
}
