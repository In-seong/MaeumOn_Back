<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FcMsgTran extends Model
{
    protected $table = 'FC_MSG_TRAN';
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

    /**
     * FC 테이블 DDL이 대문자 컬럼명(TR_SENDSTAT 등)을 사용하므로
     * SELECT 결과의 키를 소문자로 변환하여 코드와 일치시킴
     */
    public function newFromBuilder($attributes = [], $connection = null)
    {
        $lowered = [];
        foreach ((array) $attributes as $key => $value) {
            $lowered[strtolower($key)] = $value;
        }

        return parent::newFromBuilder((object) $lowered, $connection);
    }

    public function getKeyName()
    {
        return ['tr_batchid', 'tr_serialno'];
    }

    public function meta(): BelongsTo
    {
        return $this->belongsTo(FcMetaTran::class, 'tr_batchid', 'tr_batchid');
    }
}
