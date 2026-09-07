<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Notification;
use App\Services\FcmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotifyBirthday extends Command
{
    protected $signature = 'notification:birthday';
    protected $description = '오늘 생일인 고객의 담당 설계사에게 알림 발송';

    public function handle(FcmService $fcmService): int
    {
        $today = now();
        $month = (int) $today->format('m');
        $day = (int) $today->format('d');

        $customers = Customer::where('is_active', true)
            ->whereNotNull('agent_id')
            ->whereNotNull('birth_date')
            ->whereMonth('birth_date', $month)
            ->whereDay('birth_date', $day)
            ->get(['customer_id', 'name', 'agent_id', 'birth_date']);

        if ($customers->isEmpty()) {
            $this->line('오늘 생일인 고객이 없습니다.');
            return self::SUCCESS;
        }

        $this->info("오늘 생일인 고객: {$customers->count()}명");

        $grouped = $customers->groupBy('agent_id');

        foreach ($grouped as $agentId => $agentCustomers) {
            $names = $agentCustomers->pluck('name')->join(', ');
            $count = $agentCustomers->count();
            $title = '고객 생일 알림';
            $content = $count === 1
                ? "오늘은 '{$names}'님의 생일입니다."
                : "오늘 생일인 고객이 {$count}명 있습니다: {$names}";

            Notification::create([
                'receiver_id' => $agentId,
                'receiver_type' => 'AGENT',
                'sender_type' => 'SYSTEM',
                'notification_type' => 'BIRTHDAY',
                'title' => $title,
                'content' => $content,
                'is_read' => false,
                'sent_at' => now(),
                'created_at' => now(),
            ]);

            try {
                $fcmService->sendToUsers('AGENT', [$agentId], $title, $content);
            } catch (\Exception $e) {
                Log::error('FCM 생일 알림 실패', ['agent_id' => $agentId, 'error' => $e->getMessage()]);
            }

            $this->line("설계사 {$agentId}: {$names}");
        }

        return self::SUCCESS;
    }
}
