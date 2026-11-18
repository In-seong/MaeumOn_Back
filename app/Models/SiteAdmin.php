<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiteAdmin extends Model
{
    protected $table = 'site_admin';

    protected $fillable = [
        'username', 'password', 'name', 'phone', 'email', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    public function successCases(): HasMany
    {
        return $this->hasMany(SiteSuccessCase::class, 'author_id');
    }
}
