<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ClaimDocument extends Model
{
    protected $table = 'claim_document';
    protected $primaryKey = 'claim_document_id';

    protected $fillable = [
        'claim_id',
        'supporting_document_id',
        'document_file_url',
        'document_file_name',
        'document_file_size',
        'upload_status',
        'created_by_id',
        'updated_by_id',
    ];

    protected $appends = ['document_url'];

    protected $casts = [
        'document_file_size' => 'integer',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(InsuranceClaim::class, 'claim_id', 'claim_id');
    }

    /**
     * S3 pre-signed URL (30분)
     */
    public function getDocumentUrlAttribute(): ?string
    {
        if ($this->document_file_url) {
            $ttl = config('filesystems.s3_private_url_ttl', 60);
            return Storage::disk('s3')->temporaryUrl($this->document_file_url, now()->addMinutes($ttl));
        }
        return null;
    }
}
