<?php

namespace App\Services;

use App\Models\FcMetaTran;
use App\Models\FcMsgTran;
use App\Models\InsuranceClaim;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FaxService
{
    protected string $relayId;
    protected string $senderFaxNumber;
    protected string $senderName;
    protected string $sendDocPath;

    public function __construct()
    {
        $this->relayId = config('webfax.relay_id');
        $this->senderFaxNumber = config('webfax.sender_fax_number');
        $this->senderName = config('webfax.sender_name');
        $this->sendDocPath = config('webfax.send_doc_path');
    }

    /**
     * 팩스 발송 (FC 테이블 INSERT 방식)
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

        // S3에서 PDF 다운로드 → FaxClientNC SendDoc 디렉토리로 저장
        $docFileName = "claim_{$claim->claim_id}_" . time() . '.pdf';
        $sendDocFullPath = rtrim($this->sendDocPath, '/') . '/' . $docFileName;

        try {
            $pdfContent = Storage::disk('s3')->get($claim->generated_pdf_path);
            if (file_put_contents($sendDocFullPath, $pdfContent) === false) {
                throw new \RuntimeException('파일 쓰기 실패');
            }
        } catch (\Exception $e) {
            Log::error('FaxClientNC: S3 → SendDoc 파일 저장 실패', [
                'claim_id' => $claim->claim_id,
                's3_path' => $claim->generated_pdf_path,
                'local_path' => $sendDocFullPath,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => '팩스 발송 파일 준비에 실패했습니다.',
            ];
        }

        try {
            DB::beginTransaction();

            // 1. fc_meta_tran INSERT (tr_sendstat='-': 아직 FaxClientNC가 픽업하면 안됨)
            $meta = FcMetaTran::create([
                'tr_type' => '1',
                'tr_senddate' => now(),
                'tr_id' => $this->relayId,
                'tr_title' => "Claim-{$claim->claim_id}",
                'tr_sendname' => 'MaeumOn',
                'tr_sendfaxnum' => $this->senderFaxNumber,
                'tr_msgcount' => 1,
                'tr_docname' => $docFileName,
                'tr_sendstat' => '-',
            ]);

            $batchId = $meta->tr_batchid;

            // 2. fc_msg_tran INSERT (수신자 정보)
            FcMsgTran::create([
                'tr_batchid' => $batchId,
                'tr_serialno' => 1,
                'tr_senddate' => now(),
                'tr_name' => $insuranceCompany->company_name,
                'tr_phone' => $targetFaxNumber,
                'tr_sendstat' => '0',
                'tr_rsltstat' => '-',
            ]);

            // 3. fc_meta_tran UPDATE tr_sendstat='0' (FaxClientNC가 픽업 가능)
            $meta->update(['tr_sendstat' => '0']);

            DB::commit();

            Log::info('FaxClientNC: 팩스 발송 요청 등록', [
                'claim_id' => $claim->claim_id,
                'batch_id' => $batchId,
                'customer_id' => $claim->customer_id,
                'insurance_company' => $insuranceCompany->company_name,
                'fax_number' => $targetFaxNumber,
                'doc_name' => $docFileName,
            ]);

            // S3 파일 접근 제어
            $this->makeClaimFilesPrivate($claim);

            return [
                'success' => true,
                'message' => "팩스가 {$this->formatFaxNumber($targetFaxNumber)}로 발송 요청되었습니다.",
                'fax_number' => $targetFaxNumber,
                'reference_id' => (string) $batchId,
                'sent_at' => now()->toDateTimeString(),
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            // 실패 시 복사한 파일 정리
            if (file_exists($sendDocFullPath)) {
                @unlink($sendDocFullPath);
            }

            Log::error('FaxClientNC: 팩스 발송 요청 실패', [
                'claim_id' => $claim->claim_id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => '팩스 발송 요청 중 오류가 발생했습니다: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * 팩스 발송 상태 확인 (FC_MSG_TRAN 조회)
     */
    public function checkFaxStatus(int $batchId): array
    {
        $messages = FcMsgTran::where('tr_batchid', $batchId)->get();

        if ($messages->isEmpty()) {
            return [
                'success' => false,
                'message' => '팩스 발송 정보를 찾을 수 없습니다.',
            ];
        }

        $meta = FcMetaTran::find($batchId);

        $items = [];
        foreach ($messages as $msg) {
            $items[] = [
                'serial_no' => $msg->tr_serialno,
                'receiver_name' => $msg->tr_name,
                'receiver_fax' => $msg->tr_phone,
                'send_stat' => $msg->tr_sendstat,
                'result_stat' => $msg->tr_rsltstat,
                'result_message' => $this->getResultMessage($msg->tr_rsltstat),
                'send_time' => $msg->tr_sendtime,
                'recv_time' => $msg->tr_recvtime,
                'page_count' => $msg->tr_pagecnt,
            ];
        }

        return [
            'success' => true,
            'batch_id' => $batchId,
            'meta_status' => $meta?->tr_sendstat,
            'items' => $items,
        ];
    }

    /**
     * LG U+ 결과코드 → 한글 메시지 변환
     */
    public function getResultMessage(string $code): string
    {
        if ($code === '-') {
            return '결과 대기중';
        }

        $resultCodes = config('webfax.result_codes', []);

        return $resultCodes[$code] ?? "알 수 없는 결과 (코드: {$code})";
    }

    /**
     * 팩스번호 포맷팅
     */
    private function formatFaxNumber(string $number): string
    {
        $number = preg_replace('/[^0-9]/', '', $number);

        if (strlen($number) === 11) {
            return substr($number, 0, 3) . '-' . substr($number, 3, 4) . '-' . substr($number, 7);
        } elseif (strlen($number) === 10) {
            if (substr($number, 0, 2) === '02') {
                return substr($number, 0, 2) . '-' . substr($number, 2, 4) . '-' . substr($number, 6);
            }
            return substr($number, 0, 3) . '-' . substr($number, 3, 3) . '-' . substr($number, 6);
        } elseif (strlen($number) === 9) {
            return substr($number, 0, 2) . '-' . substr($number, 2, 3) . '-' . substr($number, 5);
        }

        return $number;
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
