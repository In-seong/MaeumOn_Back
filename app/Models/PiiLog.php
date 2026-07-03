<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PiiLog extends Model
{
    protected $table = 'pii_logs';
    public $timestamps = false;

    protected $fillable = [
        'admin_id',
        'action',
        'target_type',
        'target_id',
        'field',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public static function log(
        string $adminId,
        string $action,
        string $targetType,
        string $targetId,
        string $field,
        ?string $ip = null
    ): void {
        self::create([
            'admin_id' => $adminId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'field' => $field,
            'ip_address' => $ip ?? request()->ip(),
            'created_at' => now(),
        ]);
    }
}
