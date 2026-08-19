<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Admin extends Model
{
    protected $table = 'admin';
    protected $primaryKey = 'admin_id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'admin_id',
        'account_id',
        'name',
        'phone',
        'email',
        'department',
        'branch_id',
        'admin_role',
        'position',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function isSuperAdmin(): bool
    {
        return $this->admin_role === 'SUPER';
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id', 'account_id');
    }

    public function notices(): HasMany
    {
        return $this->hasMany(Notice::class, 'author_id', 'admin_id');
    }

    public function customerAssignments(): HasMany
    {
        return $this->hasMany(CustomerAssignment::class, 'admin_id', 'admin_id');
    }
}
