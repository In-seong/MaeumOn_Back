<?php

namespace App\Services;

use App\Models\InsuranceClaim;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Popbill\FaxService as PopbillFaxService;
use Popbill\PopbillException;

class FaxService
{
    protected PopbillFaxService $popbillFax;
    protected string $corpNum;
    protected string $senderNumber;
    protected bool $isTest;

    public function __construct()
    {
        $linkId = config('popbill.link_id');
        $secretKey = config('popbill.secret_key');

        $this->corpNum = config('popbill.corp_num');
        $this->senderNumber = config('popbill.fax_sender_number');
        $this->isTest = config('popbill.is_test', true);

        // 팝빌 팩스 서비스 초기화
        $this->popbillFax = new PopbillFaxService($linkId, $secretKey);
        $this->popbillFax->IsTest($this->isTest);
        $this->popbillFax->IPRestrictOnOff(config('popbill.ip_restrict_on_off', true));
        $this->popbillFax->UseStaticIP(config('popbill.use_static_ip', false));
        $this->popbillFax->UseLocalTimeYN(config('popbill.use_local_time_yn', true));
    }

    /**
     * 팩스 발송
     */
    public function sendFax(InsuranceClaim $claim, ?string $faxNumber = null): array
    {
        $template = $claim->claimFormTemplate;
        $insuranceCompany = $template->insuranceCompany;

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

        // PDF 파일 확인 및 생성
        if (!$claim->generated_pdf_path) {
            $pdfService = new PdfGeneratorService();
            $pdfPath = $pdfService->generateClaimPdf($claim);
            $claim->update(['generated_pdf_path' => $pdfPath]);
        }

        // PDF 파일 경로
        $pdfFullPath = Storage::disk('public')->path($claim->generated_pdf_path);

        if (!file_exists($pdfFullPath)) {
            return [
                'success' => false,
                'message' => 'PDF 파일을 찾을 수 없습니다.',
            ];
        }

        try {
            // 수신자 정보 설정
            $receivers = [];
            $receivers[] = [
                'rcvNum' => $targetFaxNumber,           // 수신번호
                'rcvName' => $insuranceCompany->name,   // 수신자명
            ];

            // 전송 파일 목록
            $filePaths = [$pdfFullPath];

            // 팩스 발송
            $receiptNum = $this->popbillFax->SendFAX(
                $this->corpNum,           // 사업자번호
                $this->senderNumber,      // 발신번호
                $receivers,               // 수신자 정보
                $filePaths,               // 파일 목록
                null,                     // 예약전송일시 (null: 즉시전송)
                null,                     // 팝빌회원 아이디
                '마음온',                  // 발신자명
                false,                    // 광고팩스 여부
                "보험청구서 - {$claim->id}"  // 팩스 제목
            );

            // 로그 기록
            Log::info('Popbill Fax Send Success', [
                'claim_id' => $claim->id,
                'user_id' => $claim->user_id,
                'insurance_company' => $insuranceCompany->name,
                'fax_number' => $targetFaxNumber,
                'receipt_num' => $receiptNum,
            ]);

            return [
                'success' => true,
                'message' => "팩스가 {$this->formatFaxNumber($targetFaxNumber)}로 발송 요청되었습니다.",
                'fax_number' => $targetFaxNumber,
                'reference_id' => $receiptNum,
                'sent_at' => now()->toDateTimeString(),
            ];

        } catch (PopbillException $e) {
            // 팝빌 에러 처리
            Log::error('Popbill Fax Send Failed', [
                'claim_id' => $claim->id,
                'user_id' => $claim->user_id,
                'fax_number' => $targetFaxNumber,
                'error_code' => $e->getCode(),
                'error_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $this->getErrorMessage($e->getCode(), $e->getMessage()),
                'fax_number' => $targetFaxNumber,
                'error_code' => $e->getCode(),
            ];
        } catch (\Exception $e) {
            Log::error('Fax Send Exception', [
                'claim_id' => $claim->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => '팩스 발송 중 오류가 발생했습니다: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * 팩스 발송 상태 확인
     */
    public function checkFaxStatus(string $receiptNum): array
    {
        try {
            $result = $this->popbillFax->GetFaxDetail(
                $this->corpNum,
                $receiptNum
            );

            // 상태 코드 해석
            $statusMap = [
                0 => '대기중',
                1 => '전송중',
                2 => '전송성공',
                3 => '전송실패',
                4 => '예약취소',
            ];

            $items = [];
            foreach ($result as $item) {
                $items[] = [
                    'send_num' => $item->sendNum ?? '',
                    'receive_num' => $item->receiveNum ?? '',
                    'receive_name' => $item->receiveName ?? '',
                    'state' => $item->state ?? 0,
                    'state_label' => $statusMap[$item->state ?? 0] ?? '알수없음',
                    'result' => $item->result ?? 0,
                    'send_page_cnt' => $item->sendPageCnt ?? 0,
                    'success_page_cnt' => $item->successPageCnt ?? 0,
                    'fail_page_cnt' => $item->failPageCnt ?? 0,
                    'ref_undone_cnt' => $item->refundPageCnt ?? 0,
                    'send_date_time' => $item->sendDT ?? '',
                    'result_date_time' => $item->resultDT ?? '',
                ];
            }

            return [
                'success' => true,
                'reference_id' => $receiptNum,
                'items' => $items,
            ];

        } catch (PopbillException $e) {
            return [
                'success' => false,
                'reference_id' => $receiptNum,
                'message' => $this->getErrorMessage($e->getCode(), $e->getMessage()),
                'error_code' => $e->getCode(),
            ];
        }
    }

    /**
     * 팩스 발송 취소 (예약 전송인 경우)
     */
    public function cancelFax(string $receiptNum): array
    {
        try {
            $result = $this->popbillFax->CancelReserve(
                $this->corpNum,
                $receiptNum
            );

            return [
                'success' => true,
                'message' => '팩스 예약이 취소되었습니다.',
            ];

        } catch (PopbillException $e) {
            return [
                'success' => false,
                'message' => $this->getErrorMessage($e->getCode(), $e->getMessage()),
                'error_code' => $e->getCode(),
            ];
        }
    }

    /**
     * 잔여 포인트 조회
     */
    public function getBalance(): array
    {
        try {
            $balance = $this->popbillFax->GetBalance($this->corpNum);

            return [
                'success' => true,
                'balance' => $balance,
            ];

        } catch (PopbillException $e) {
            return [
                'success' => false,
                'message' => $this->getErrorMessage($e->getCode(), $e->getMessage()),
                'balance' => 0,
            ];
        }
    }

    /**
     * 단가 조회
     */
    public function getUnitCost(): array
    {
        try {
            $unitCost = $this->popbillFax->GetUnitCost($this->corpNum);

            return [
                'success' => true,
                'unit_cost' => $unitCost,
            ];

        } catch (PopbillException $e) {
            return [
                'success' => false,
                'message' => $this->getErrorMessage($e->getCode(), $e->getMessage()),
            ];
        }
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
     * 에러 메시지 변환
     */
    private function getErrorMessage(int $code, string $message): string
    {
        $errorMessages = [
            -11000001 => '팝빌 회원 아이디가 존재하지 않습니다.',
            -11000002 => '팝빌 연동 정보가 올바르지 않습니다.',
            -12000004 => '잔여 포인트가 부족합니다.',
            -12000009 => '발신번호가 등록되지 않았습니다. 팝빌에서 발신번호를 먼저 등록해주세요.',
            -12000016 => '수신번호가 올바르지 않습니다.',
            -12000018 => '전송 파일을 찾을 수 없습니다.',
            -12000019 => '파일 형식이 올바르지 않습니다. (PDF, TIF, TIFF 지원)',
            -99999999 => '서버 오류가 발생했습니다. 잠시 후 다시 시도해주세요.',
        ];

        return $errorMessages[$code] ?? "팩스 발송에 실패했습니다. (오류코드: {$code}) {$message}";
    }
}
