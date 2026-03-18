<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class InsuranceClaim extends Model
{
    // 상태 상수 (물리설계 기준)
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_PAID = 'paid';

    const VALID_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_PAID,
    ];

    // 허용되는 상태 전이 규칙
    const ALLOWED_TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_PENDING],
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
        'batch_claim_id',
        'insurance_id',
        'company_id',
        'agent_id',
        'claim_form_id',
        'claim_number',
        'claim_type',
        'accident_date',
        'claim_amount',
        'approved_amount',
        'paid_date',
        'paid_amount',
        'claim_status',
        'claim_date',
        'approval_date',
        'rejection_reason',
        'generated_pdf_path',
        'fax_sent_at',
        'fax_status',
        'fax_batch_id',
        'fax_number_sent',
        'fax_result_code',
        'notes',
        'created_by_id',
        'updated_by_id',
    ];

    protected $appends = ['generated_pdf_url', 'generated_image_urls'];

    protected $casts = [
        'accident_date' => 'date',
        'claim_date' => 'date',
        'approval_date' => 'date',
        'paid_date' => 'date',
        'fax_sent_at' => 'datetime',
        'claim_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public function getStatusLabelAttribute(): string
    {
        return match($this->claim_status) {
            self::STATUS_DRAFT => '임시저장',
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

    /**
     * 생성된 페이지별 이미지 URL 배열 (WebView PDF 미지원 대응)
     */
    public function getGeneratedImageUrlsAttribute(): array
    {
        if (!$this->generated_pdf_path) {
            return [];
        }

        $ttl = config('filesystems.s3_private_url_ttl', 60);
        $dir = 'claims/' . $this->claim_id;
        $files = Storage::disk('s3')->files($dir);

        $imageUrls = [];
        foreach ($files as $file) {
            if (preg_match('/page_(\d+)\.jpg$/', $file, $matches)) {
                $imageUrls[] = [
                    'page_number' => (int) $matches[1],
                    'url' => Storage::disk('s3')->temporaryUrl($file, now()->addMinutes($ttl)),
                ];
            }
        }

        // 페이지 번호 순 정렬
        usort($imageUrls, fn($a, $b) => $a['page_number'] - $b['page_number']);

        return $imageUrls;
    }

    public function batchClaim()
    {
        return $this->belongsTo(BatchClaim::class, 'batch_claim_id', 'batch_claim_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
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

    public function documents()
    {
        return $this->hasMany(ClaimDocument::class, 'claim_id', 'claim_id');
    }

    /**
     * 팩스 상태 한글 라벨
     */
    public function getFaxStatusLabelAttribute(): ?string
    {
        if (!$this->fax_status) {
            return null;
        }

        return match ($this->fax_status) {
            'pending' => '발송 대기',
            'sending' => '발송 중',
            'sent' => '발송 완료',
            'failed' => '발송 실패',
            default => $this->fax_status,
        };
    }

    /**
     * FC 팩스 배치 메타 정보
     */
    public function fcMetaTran()
    {
        return $this->hasOne(FcMetaTran::class, 'tr_batchid', 'fax_batch_id');
    }
}
