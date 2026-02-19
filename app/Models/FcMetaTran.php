<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FcMetaTran extends Model
{
    protected $table = 'FC_META_TRAN';
    protected $primaryKey = 'tr_batchid';
    public $timestamps = false;

    protected $fillable = [
        'tr_type',
        'tr_senddate',
        'tr_id',
        'tr_title',
        'tr_sendname',
        'tr_sendfaxnum',
        'tr_msgcount',
        'tr_docname',
        'tr_sendstat',
        'tr_reservetime',
    ];

    protected $casts = [
        'tr_senddate' => 'datetime',
        'tr_reservetime' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(FcMsgTran::class, 'tr_batchid', 'tr_batchid');
    }
}
