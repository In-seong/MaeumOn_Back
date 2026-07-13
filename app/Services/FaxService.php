<?php

namespace App\Services;

use App\Models\InsuranceClaim;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FaxService
{
    protected string $uid;
    protected string $pwd;
    protected string $fromNumber;
    protected string $faxUrl;

    public function __construct()
    {
        $this->uid = config('bizmoa.uid');
        $this->pwd = config('bizmoa.pwd');
        $this->fromNumber = config('bizmoa.from_number');
        $this->faxUrl = config('bizmoa.fax_url');
    }

    /**
     * 팩스 발송 (S3의 PDF 파일)
     */
    public function sendFax(InsuranceClaim $claim, ?string $faxNumber = null): array
    {
        $claimForm = $claim->claimForm;
        $insuranceCompany = $claimForm->insuranceCompany;

        $targetFaxNumber = $faxNumber ?? $insuranceCompany->fax_number;

        if (!$targetFaxNumber) {
            return ['success' => false, 'message' => '팩스 번호가 없습니다.'];
        }

        $targetFaxNumber = preg_replace('/[^0-9]/', '', $targetFaxNumber);

        if (!$claim->generated_pdf_path) {
            return ['success' => false, 'message' => 'PDF 파일이 생성되지 않았습니다.'];
        }

        if (!Storage::disk('s3')->exists($claim->generated_pdf_path)) {
            return ['success' => false, 'message' => 'PDF 파일을 찾을 수 없습니다.'];
        }

        try {
            $pdfContent = Storage::disk('s3')->get($claim->generated_pdf_path);
        } catch (\Exception $e) {
            Log::error('BizMoaShot: S3 PDF 다운로드 실패', [
                'claim_id' => $claim->claim_id,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => '팩스 발송 파일 준비에 실패했습니다.'];
        }

        return $this->sendFaxViaBizmoa($claim, $pdfContent, $targetFaxNumber, $insuranceCompany->company_name);
    }

    /**
     * 팩스 발송 (PDF 바이너리 직접 전달 — 병합 PDF용)
     */
    public function sendFaxWithContent(InsuranceClaim $claim, string $pdfBinary, ?string $faxNumber = null): array
    {
        $claimForm = $claim->claimForm;
        $insuranceCompany = $claimForm->insuranceCompany;

        $targetFaxNumber = $faxNumber ?? $insuranceCompany->fax_number;

        if (!$targetFaxNumber) {
            return ['success' => false, 'message' => '팩스 번호가 없습니다.'];
        }

        $targetFaxNumber = preg_replace('/[^0-9]/', '', $targetFaxNumber);

        return $this->sendFaxViaBizmoa($claim, $pdfBinary, $targetFaxNumber, $insuranceCompany->company_name);
    }

    /**
     * 비즈모아샷 URL연동 팩스 발송
     */
    private function sendFaxViaBizmoa(InsuranceClaim $claim, string $pdfBinary, string $toNumber, string $receiverName): array
    {
        $indexCode = "claim_{$claim->claim_id}_" . time();
        $timestamp = time();
        $fileName = "claim_{$claim->claim_id}_{$timestamp}.pdf";

        // 1. FTP로 PDF 업로드
        $ftpResult = $this->uploadToFtp($fileName, $pdfBinary);
        if (!$ftpResult) {
            return ['success' => false, 'message' => 'FTP 파일 업로드에 실패했습니다.'];
        }

        // 2. 비즈모아샷 API 호출
        $params = [
            'uid' => $this->uid,
            'pwd' => $this->pwd,
            'sendType' => '1',
            'toNumber' => $toNumber,
            'fromNumber' => $this->fromNumber,
            'contType' => '1',
            'fileName' => $fileName,
            'title' => "Claim-{$claim->claim_id}",
            'indexCode' => $indexCode,
            'returnType' => '3',
            'utfSave' => '1',
            'nType' => '3',
        ];

        // 보안 설정 (commType=2)
        $secCode = config('bizmoa.sec_code');
        if ($secCode) {
            $params['commType'] = '2';
            $params['commCode'] = md5($this->uid . $toNumber . $secCode);
        }

        // 결과 콜백 URL
        $returnUrl = config('bizmoa.return_url');
        if ($returnUrl) {
            $params['returnUrl'] = $returnUrl;
        }

        try {
            $response = Http::timeout(30)
                ->asForm()
                ->post($this->faxUrl, $params);

            $responseBody = trim($response->body());

            Log::info('BizMoaShot: 팩스 발송 요청', [
                'claim_id' => $claim->claim_id,
                'to_number' => $toNumber,
                'index_code' => $indexCode,
                'file_name' => $fileName,
                'response' => $responseBody,
                'status_code' => $response->status(),
            ]);

            if (str_starts_with($responseBody, 'SUCCESS')) {
                $this->makeClaimFilesPrivate($claim);

                return [
                    'success' => true,
                    'message' => "팩스가 {$this->formatFaxNumber($toNumber)}로 발송 요청되었습니다.",
                    'fax_number' => $toNumber,
                    'reference_id' => $indexCode,
                    'sent_at' => now()->toDateTimeString(),
                ];
            }

            Log::error('BizMoaShot: 팩스 발송 실패 응답', [
                'claim_id' => $claim->claim_id,
                'response' => $responseBody,
            ]);

            return [
                'success' => false,
                'message' => '팩스 발송 요청이 실패했습니다: ' . $responseBody,
            ];

        } catch (\Exception $e) {
            Log::error('BizMoaShot: 팩스 API 호출 실패', [
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
     * 비즈모아샷 FTP 서버에 파일 업로드
     */
    private function uploadToFtp(string $fileName, string $content): bool
    {
        $host = config('bizmoa.ftp_host');
        $port = (int) config('bizmoa.ftp_port', 21);
        $user = config('bizmoa.ftp_user');
        $pass = config('bizmoa.ftp_pass');

        if (!$host || !$user) {
            Log::error('BizMoaShot: FTP 설정이 없습니다.');
            return false;
        }

        try {
            $ftp = ftp_connect($host, $port, 30);
            if (!$ftp) {
                throw new \RuntimeException("FTP 연결 실패: {$host}:{$port}");
            }

            if (!ftp_login($ftp, $user, $pass)) {
                ftp_close($ftp);
                throw new \RuntimeException('FTP 로그인 실패');
            }

            ftp_pasv($ftp, true);

            $tmpFile = tempnam(sys_get_temp_dir(), 'bizmoa_');
            file_put_contents($tmpFile, $content);

            $result = ftp_put($ftp, $fileName, $tmpFile, FTP_BINARY);

            @unlink($tmpFile);
            ftp_close($ftp);

            if (!$result) {
                throw new \RuntimeException('FTP 파일 업로드 실패');
            }

            Log::info('BizMoaShot: FTP 업로드 완료', [
                'file' => $fileName,
                'size' => round(strlen($content) / 1024) . 'KB',
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('BizMoaShot: FTP 업로드 실패', [
                'file' => $fileName,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 팩스 발송 결과 콜백 처리 (비즈모아샷 → 우리 서버)
     */
    public function handleCallback(array $params): void
    {
        $data = $params['data'] ?? null;

        if (!$data) {
            Log::warning('BizMoaShot callback: data 파라미터 없음', $params);
            return;
        }

        // data 형식: "indexCode,resultCode"
        $parts = explode(',', $data);
        $indexCode = $parts[0] ?? '';
        $resultCode = $parts[1] ?? '';

        if (!$indexCode) {
            Log::warning('BizMoaShot callback: indexCode 없음', $params);
            return;
        }

        $claim = InsuranceClaim::where('fax_batch_id', $indexCode)->first();

        if (!$claim) {
            Log::warning('BizMoaShot callback: 청구 건 없음', [
                'index_code' => $indexCode,
                'result_code' => $resultCode,
            ]);
            return;
        }

        if ($resultCode === '1') {
            $claim->update([
                'fax_status' => 'sent',
                'fax_result_code' => $resultCode,
                'claim_status' => 'processing',
            ]);
        } else {
            $claim->update([
                'fax_status' => 'failed',
                'fax_result_code' => $resultCode,
            ]);
        }

        Log::info('BizMoaShot callback: 팩스 결과 수신', [
            'claim_id' => $claim->claim_id,
            'index_code' => $indexCode,
            'result_code' => $resultCode,
            'result_message' => $this->getResultMessage($resultCode),
            'send_start' => $params['Send_start'] ?? null,
            'send_end' => $params['Send_end'] ?? null,
            'page' => $params['page'] ?? null,
        ]);
    }

    /**
     * 팩스 발송 결과코드 → 한글 메시지
     */
    public function getResultMessage(string $code): string
    {
        if ($code === '' || $code === '-') {
            return '결과 대기중';
        }

        $resultCodes = config('bizmoa.fax_result_codes', []);

        return $resultCodes[$code] ?? "알 수 없는 결과 (코드: {$code})";
    }

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

    private function makeClaimFilesPrivate(InsuranceClaim $claim): void
    {
        // BucketOwnerEnforced 정책: ACL 사용 불가
        // S3 파일은 기본적으로 private이므로 별도 처리 불필요
    }
}
