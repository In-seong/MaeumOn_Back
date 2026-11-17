<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AgentInquiry extends Model
{
    protected $table = 'agent_inquiry';
    protected $primaryKey = 'inquiry_id';

    protected $fillable = [
        'agent_id',
        'category',
        'title',
        'content',
        'attachments',
        'answer',
        'answered_by',
        'answered_at',
        'status',
    ];

    protected $casts = [
        'attachments' => 'array',
        'answered_at' => 'datetime',
    ];

    protected $appends = ['attachment_urls'];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    public function answeredByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'answered_by', 'admin_id');
    }

    public function getAttachmentUrlsAttribute(): array
    {
        $attachments = $this->attachments;
        if (empty($attachments)) return [];

        return array_map(function ($file) {
            $path = $file['path'] ?? '';
            if (!$path) return $file;
            return array_merge($file, [
                'url' => Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(60)),
            ]);
        }, $attachments);
    }
}
