<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Memo extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
    protected $table = 'memo';
    protected $primaryKey = 'memo_id';

    protected $fillable = [
        'customer_id',
        'author_id',
        'author_type',
        'title',
        'content',
        'category',
        'progress',
        'memo_date',
        'attachments',
    ];

    protected $casts = [
        'memo_date' => 'date:Y-m-d',
        'attachments' => 'array',
    ];

    protected $appends = ['attachment_urls'];

    public function getAttachmentUrlsAttribute(): array
    {
        if (empty($this->attachments)) return [];

        return collect($this->attachments)->map(function ($a) {
            $path = is_string($a) ? $a : ($a['path'] ?? '');
            $name = is_string($a) ? basename($a) : ($a['name'] ?? basename($path));
            return [
                'path' => $path,
                'url' => \Illuminate\Support\Facades\Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(60)),
                'name' => $name,
            ];
        })->all();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }
}
