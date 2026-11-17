<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnclaimedConsent extends Model
{
    protected $table = 'unclaimed_consent';
    protected $primaryKey = 'consent_id';

    protected $fillable = [
        'session_id',
        'consent_template_id',
        'agreed_at',
        'ip_address',
    ];

    protected $casts = [
        'agreed_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(UnclaimedSession::class, 'session_id', 'session_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ConsentTemplate::class, 'consent_template_id', 'consent_template_id');
    }
}
