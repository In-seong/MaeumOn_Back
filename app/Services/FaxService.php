<?php

namespace App\Services;

use App\Models\InsuranceClaim;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FaxService
{
    /**
     * 팩스 발송 (스텁 - LG API 연동 예정)
     */
    public function sendFax(InsuranceClaim $claim, ?string $faxNumber = null): array
    {
        $claimForm = $claim->claimForm;
        $insuranceCompany = $claimForm->insuranceCompany;

        // 팩스 번호 결정 (입력값 > 보험사 기본값)
        $targetFaxNumber = $faxNumber ?? $insuranceCompany->fax_number;

        if (!$targetFaxNumber) {
            return [
                'success' => false,
                'message' => '팩스 번호가 없습니다.',
            ];
        }

        // 팩스번호 정규화 (하이픈 제거)
        $targetFaxNumber = preg_replace('/[^0-9]/', '', $targetFaxNumber);

        // PDF 파일 존재 확인
        if (!$claim->generated_pdf_path) {
            return [
                'success' => false,
                'message' => 'PDF 파일이 생성되지 않았습니다.',
            ];
        }

        if (!Storage::disk('s3')->exists($claim->generated_pdf_path)) {
            return [
                'success' => false,
                'message' => 'PDF 파일을 찾을 수 없습니다.',
            ];
        }

        // TODO: LG 팩스 API 연동 구현
        // 현재는 발송 성공으로 처리

        Log::info('Fax Send Stub', [
            'claim_id' => $claim->claim_id,
            'customer_id' => $claim->customer_id,
            'insurance_company' => $insuranceCompany->company_name,
            'fax_number' => $targetFaxNumber,
        ]);

        // 팩스 전송 후 S3 파일을 private로 전환
        $this->makeClaimFilesPrivate($claim);

        return [
            'success' => true,
            'message' => '팩스 발송이 요청되었습니다. (테스트 모드)',
            'fax_number' => $targetFaxNumber,
            'sent_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * S3 파일 접근 제어 (BucketOwnerEnforced - ACL 비활성 버킷)
     * 파일은 기본적으로 private이며, pre-signed URL로 접근
     */
    private function makeClaimFilesPrivate(InsuranceClaim $claim): void
    {
        // BucketOwnerEnforced 정책: ACL 사용 불가
        // S3 파일은 기본적으로 private이므로 별도 처리 불필요
    }
}
