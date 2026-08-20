<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributionConfig extends Model
{
    protected $table = 'distribution_config';
    protected $primaryKey = 'config_id';

    protected $fillable = [
        'branch_id',
        'is_active',
        'current_list_id',
        'current_position',
        'delay_minutes',
        'timeout_hours',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'current_position' => 'integer',
        'delay_minutes' => 'integer',
        'timeout_hours' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    public function currentList(): BelongsTo
    {
        return $this->belongsTo(DistributionList::class, 'current_list_id', 'list_id');
    }
}
