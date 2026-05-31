<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ClaimRequestFile extends Model
{
    protected $table = 'claim_request_file';
    protected $primaryKey = 'file_id';
    public $timestamps = false;

    protected $fillable = [
        'request_id',
        'file_url',
        'file_name',
        'file_size',
    ];

    protected $appends = ['file_download_url'];

    public function claimRequest(): BelongsTo
    {
        return $this->belongsTo(ClaimRequest::class, 'request_id', 'request_id');
    }

    public function getFileDownloadUrlAttribute(): ?string
    {
        if (!$this->file_url) {
            return null;
        }
        return Storage::disk('s3')->temporaryUrl($this->file_url, now()->addMinutes(30));
    }
}
