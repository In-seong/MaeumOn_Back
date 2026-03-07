<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $table = 'customer';
    protected $primaryKey = 'customer_id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'customer_id',
        'account_id',
        'agent_id',
        'name',
        'resident_number',
        'gender',
        'birth_date',
        'phone',
        'email',
        'address',
        'detailed_address',
        'job',
        'telecom',
        'acquisition_channel',
        'last_contact_date',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'birth_date' => 'date',
        'last_contact_date' => 'date',
    ];

    /**
     * 주민등록번호 조회 시 복호화 (평문이면 그대로 반환)
     */
    public function getResidentNumberAttribute(?string $value): ?string
    {
        if ($value === null || $value === '') return $value;

        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($value);
        } catch (\Exception $e) {
            // 아직 암호화 안 된 평문 데이터
            return $value;
        }
    }

    /**
     * 주민등록번호 저장 시 암호화
     * ※ DB 컬럼을 TEXT로 변경한 후에만 암호화 저장됨
     */
    public function setResidentNumberAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['resident_number'] = $value;
            return;
        }

        // 이미 암호화된 값이면 그대로
        try {
            \Illuminate\Support\Facades\Crypt::decryptString($value);
            $this->attributes['resident_number'] = $value;
            return;
        } catch (\Exception $e) {
            // 평문 → 암호화 시도
        }

        $this->attributes['resident_number'] = \Illuminate\Support\Facades\Crypt::encryptString($value);
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id', 'account_id');
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    public function insuranceClaims()
    {
        return $this->hasMany(InsuranceClaim::class, 'customer_id', 'customer_id');
    }

    public function batchClaims()
    {
        return $this->hasMany(BatchClaim::class, 'customer_id', 'customer_id');
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class, 'customer_id', 'customer_id');
    }

    public function memos()
    {
        return $this->hasMany(Memo::class, 'customer_id', 'customer_id');
    }

    public function consultations()
    {
        return $this->hasMany(Consultation::class, 'customer_id', 'customer_id');
    }

    public function disclosureObligations()
    {
        return $this->hasMany(DisclosureObligation::class, 'customer_id', 'customer_id');
    }

    public function medicalRecords()
    {
        return $this->hasMany(MedicalRecord::class, 'customer_id', 'customer_id');
    }
}
