<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributionListItem extends Model
{
    protected $table = 'distribution_list_item';
    protected $primaryKey = 'item_id';

    public $timestamps = false;

    protected $fillable = [
        'list_id',
        'agent_id',
        'position',
        'created_at',
    ];

    protected $casts = [
        'position' => 'integer',
        'created_at' => 'datetime',
    ];

    public function list(): BelongsTo
    {
        return $this->belongsTo(DistributionList::class, 'list_id', 'list_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }
}
