<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DistributionList extends Model
{
    protected $table = 'distribution_list';
    protected $primaryKey = 'list_id';

    protected $fillable = [
        'branch_id',
        'name',
        'content_hash',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DistributionListItem::class, 'list_id', 'list_id')
            ->orderBy('position');
    }

    public function computeContentHash(): string
    {
        $agentIds = $this->items()->orderBy('position')->pluck('agent_id')->toArray();
        return hash('sha256', implode(',', $agentIds));
    }

    public function updateContentHash(): void
    {
        $this->update(['content_hash' => $this->computeContentHash()]);
    }
}
