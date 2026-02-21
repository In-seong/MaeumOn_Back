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

    public function messages(): HasMany
    {
        return $this->hasMany(FcMsgTran::class, 'tr_batchid', 'tr_batchid');
    }
}
