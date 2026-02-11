<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class InsuranceClaim extends Model
{
    // 상태 상수 (물리설계 기준)
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_PAID = 'paid';

    const VALID_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_PAID,
    ];

    // 허용되는 상태 전이 규칙
    const ALLOWED_TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_PROCESSING, self::STATUS_REJECTED],
        self::STATUS_PROCESSING => [self::STATUS_APPROVED, self::STATUS_REJECTED],
        self::STATUS_APPROVED => [self::STATUS_PAID],
        self::STATUS_REJECTED => [],
        self::STATUS_PAID => [],
    ];

    protected $table = 'insurance_claim';
    protected $primaryKey = 'claim_id';

    protected $fillable = [
        'customer_id',
        'insurance_id',
        'company_id',
        'agent_id',
        'claim_form_id',
        'claim_number',
        'claim_type',
        'accident_date',
        'claim_amount',
        'approved_amount',
        'claim_status',
        'claim_date',
        'approval_date',
        'rejection_reason',
        'generated_pdf_path',
        'fax_sent_at',
        'fax_status',
        'notes',
        'created_by_id',
        'updated_by_id',
    ];

    protected $appends = ['generated_pdf_url'];

    protected $casts = [
        'accident_date' => 'date',
        'claim_date' => 'date',
        'approval_date' => 'date',
        'fax_sent_at' => 'datetime',
        'claim_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
    ];

    public function getStatusLabelAttribute(): string
    {
        return match($this->claim_status) {
            self::STATUS_PENDING => '접수 대기',
            self::STATUS_PROCESSING => '처리중',
            self::STATUS_APPROVED => '승인',
            self::STATUS_REJECTED => '거절',
            self::STATUS_PAID => '지급 완료',
            default => $this->claim_status ?? '알 수 없음',
        };
    }

    public function canTransitionTo(string $newStatus): bool
    {
        $allowed = self::ALLOWED_TRANSITIONS[$this->claim_status] ?? [];
        return in_array($newStatus, $allowed);
    }

    public function getGeneratedPdfUrlAttribute(): ?string
    {
        if ($this->generated_pdf_path) {
            $ttl = config('filesystems.s3_private_url_ttl', 60);
            return Storage::disk('s3')->temporaryUrl($this->generated_pdf_path, now()->addMinutes($ttl));
        }
        return null;
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function claimForm()
    {
        return $this->belongsTo(ClaimForm::class, 'claim_form_id', 'claim_form_id');
    }

    public function insuranceCompany()
    {
        return $this->belongsTo(InsuranceCompany::class, 'company_id', 'company_id');
    }

    public function fieldValues()
    {
        return $this->hasMany(ClaimFieldValue::class, 'claim_id', 'claim_id');
    }
}
