<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $table = 'branch';
    protected $primaryKey = 'branch_id';

    protected $fillable = [
        'branch_name',
        'branch_code',
        'region',
        'address',
        'contact_phone',
        'manager_admin_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'manager_admin_id', 'admin_id');
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_branch', 'branch_id', 'agent_id');
    }

    public function admins(): HasMany
    {
        return $this->hasMany(Admin::class, 'branch_id', 'branch_id');
    }
}
