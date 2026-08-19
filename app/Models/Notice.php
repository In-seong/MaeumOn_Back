<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notice extends Model
{
    protected $table = 'notice';
    protected $primaryKey = 'notice_id';

    protected $fillable = [
        'author_id',
        'title',
        'content',
        'notice_type',
        'is_pinned',
        'display_start_date',
        'display_end_date',
        'branch_id',
        'view_count',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'view_count' => 'integer',
        'display_start_date' => 'date',
        'display_end_date' => 'date',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'author_id', 'admin_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }
}
