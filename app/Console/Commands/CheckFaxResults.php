<?php

namespace App\Console\Commands;

use App\Models\FcMsgTran;
use App\Models\InsuranceClaim;
use App\Services\FaxService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckFaxResults extends Command
{
    protected $signature = 'fax:check-results';
    protected $description = 'FC 테이블에서 팩스 발송 결과를 확인하고 insurance_claims 상태를 업데이트합니다';

    public function handle(FaxService $faxService): int
    {
        $timeoutMinutes = config('webfax.result_timeout_minutes', 30);

        // pending 또는 sending 상태인 claim 조회
        $claims = InsuranceClaim::whereIn('fax_status', ['pending', 'sending'])
            ->whereNotNull('fax_batch_id')
            ->get();

        if ($claims->isEmpty()) {
            $this->line('확인할 팩스 발송 건이 없습니다.');
            return self::SUCCESS;
        }

        $this->info("확인 대상: {$claims->count()}건");

        foreach ($claims as $claim) {
            $this->processClaimFaxResult($claim, $faxService, $timeoutMinutes);
        }

        return self::SUCCESS;
    }

    private function processClaimFaxResult(
        InsuranceClaim $claim,
        FaxService $faxService,
        int $timeoutMinutes
    ): void {
        $batchId = $claim->fax_batch_id;

        // fc_msg_tran에서 결과 조회
        $msg = FcMsgTran::where('tr_batchid', $batchId)
            ->where('tr_serialno', 1)
            ->first();

        if (!$msg) {
            $this->warn("Claim #{$claim->claim_id}: batch_id={$batchId} 메시지 없음");
            return;
        }

        // tr_sendstat='1' → 발송 중 상태로 업데이트
        if ($msg->tr_sendstat === '1' && $claim->fax_status === 'pending') {
            $claim->update(['fax_status' => 'sending']);
            $this->line("Claim #{$claim->claim_id}: 발송 중 상태로 변경");
            return;
        }

        // tr_sendstat='2' → 발송 완료 (결과 확인)
        if ($msg->tr_sendstat === '2') {
            $resultCode = $msg->tr_rsltstat;

            if ((int) $resultCode === 0) {
                // 성공
                $claim->update([
                    'fax_status' => 'sent',
                    'fax_result_code' => $resultCode,
                ]);
                $this->info("Claim #{$claim->claim_id}: 발송 성공");
            } else {
                // 실패
                $resultMessage = $faxService->getResultMessage($resultCode);
                $claim->update([
                    'fax_status' => 'failed',
                    'fax_result_code' => $resultCode,
                ]);
                $this->error("Claim #{$claim->claim_id}: 발송 실패 ({$resultCode}: {$resultMessage})");
            }

            Log::info('FaxClientNC: 팩스 결과 확인', [
                'claim_id' => $claim->claim_id,
                'batch_id' => $batchId,
                'result_code' => $resultCode,
                'result_message' => $faxService->getResultMessage($resultCode),
            ]);

            return;
        }

        // 타임아웃 체크
        if ($claim->fax_sent_at && $claim->fax_sent_at->diffInMinutes(now()) > $timeoutMinutes) {
            $claim->update([
                'fax_status' => 'failed',
                'fax_result_code' => 'TIMEOUT',
            ]);

            Log::warning('FaxClientNC: 팩스 발송 타임아웃', [
                'claim_id' => $claim->claim_id,
                'batch_id' => $batchId,
                'timeout_minutes' => $timeoutMinutes,
            ]);

            $this->warn("Claim #{$claim->claim_id}: 타임아웃 ({$timeoutMinutes}분 초과)");
        }
    }
}
