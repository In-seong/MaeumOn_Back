<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Account extends Authenticatable
{
    use HasApiTokens, HasFactory;

    // Role 상수
    const ROLE_CUSTOMER = 'CUSTOMER';
    const ROLE_AGENT = 'AGENT';
    const ROLE_ADMIN = 'ADMIN';

    protected $table = 'account';
    protected $primaryKey = 'account_id';

    protected $fillable = [
        'username',
        'password_hash',
        'pin_hash',
        'role',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password_hash',
        'pin_hash',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    /**
     * Override the default password field for authentication
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isCustomer(): bool
    {
        return $this->role === self::ROLE_CUSTOMER;
    }

    public function isAgent(): bool
    {
        return $this->role === self::ROLE_AGENT;
    }

    public function customer()
    {
        return $this->hasOne(Customer::class, 'account_id', 'account_id');
    }

    public function deviceTokens()
    {
        return $this->hasMany(\App\Models\DeviceToken::class, 'account_id', 'account_id');
    }
}
