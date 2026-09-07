<?php

namespace App\Console\Commands;

use App\Models\InsuranceClaim;
use App\Models\Notification;
use App\Services\FcmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckFaxResults extends Command
{
    protected $signature = 'fax:check-results';
    protected $description = '비즈모아샷 콜백 미수신 건을 확인하고 타임아웃 처리합니다';

    public function handle(): int
    {
        $timeoutMinutes = config('bizmoa.result_timeout_minutes', 30);

        $claims = InsuranceClaim::whereIn('fax_status', ['pending', 'sending'])
            ->whereNotNull('fax_batch_id')
            ->get();

        if ($claims->isEmpty()) {
            $this->line('확인할 팩스 발송 건이 없습니다.');
            return self::SUCCESS;
        }

        $this->info("확인 대상: {$claims->count()}건");

        foreach ($claims as $claim) {
            if ($claim->fax_sent_at && $claim->fax_sent_at->diffInMinutes(now()) > $timeoutMinutes) {
                $claim->update([
                    'fax_status' => 'failed',
                    'fax_result_code' => 'TIMEOUT',
                ]);

                Log::warning('BizMoaShot: 팩스 결과 콜백 타임아웃', [
                    'claim_id' => $claim->claim_id,
                    'index_code' => $claim->fax_batch_id,
                    'timeout_minutes' => $timeoutMinutes,
                ]);

                $this->sendFaxTimeoutNotification($claim);
                $this->warn("Claim #{$claim->claim_id}: 타임아웃 ({$timeoutMinutes}분 초과)");
            } else {
                $elapsed = $claim->fax_sent_at ? $claim->fax_sent_at->diffInMinutes(now()) : 0;
                $this->line("Claim #{$claim->claim_id}: 콜백 대기 중 ({$elapsed}분 경과)");
            }
        }

        return self::SUCCESS;
    }

    private function sendFaxTimeoutNotification(InsuranceClaim $claim): void
    {
        if (!$claim->agent_id) {
            return;
        }

        $customerName = $claim->customer?->name ?? '고객';

        Notification::create([
            'receiver_id' => $claim->agent_id,
            'receiver_type' => 'AGENT',
            'sender_type' => 'SYSTEM',
            'notification_type' => 'FAX_RESULT',
            'title' => '팩스 발송 실패',
            'content' => "'{$customerName}'님 청구서 팩스 발송이 시간 초과로 실패했습니다.",
            'is_read' => false,
            'sent_at' => now(),
            'created_at' => now(),
        ]);

        try {
            app(FcmService::class)->sendToUsers('AGENT', [$claim->agent_id], '팩스 발송 실패', "'{$customerName}'님 청구서 팩스가 시간 초과로 실패했습니다.");
        } catch (\Exception $e) {
            Log::error('FCM 팩스 타임아웃 알림 실패', ['error' => $e->getMessage()]);
        }
    }
}
