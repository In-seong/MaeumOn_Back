<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FcRecvTran extends Model
{
    protected $table = 'fc_recv_tran';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'tr_msgid',
        'tr_title',
        'tr_sendfaxnum',
        'tr_recvfaxnum',
        'tr_recvtime',
        'tr_filenamelist',
    ];
}
