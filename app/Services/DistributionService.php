<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Customer;
use App\Models\CustomerAssignment;
use App\Models\DistributionConfig;
use App\Models\DistributionList;
use App\Models\DistributionQueue;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DistributionService
{
    private FcmService $fcmService;

    public function __construct(FcmService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * 신규 고객을 자동배분 대기열에 등록
     */
    public function enqueueCustomer(string $customerId, int $branchId): ?DistributionQueue
    {
        $config = DistributionConfig::where('branch_id', $branchId)->first();

        if (!$config || !$config->is_active || !$config->current_list_id) {
            return null;
        }

        $existing = DistributionQueue::where('customer_id', $customerId)
            ->whereIn('status', ['pending', 'assigned'])
            ->first();

        if ($existing) {
            return null;
        }

        return DistributionQueue::create([
            'customer_id' => $customerId,
            'branch_id' => $branchId,
            'status' => 'pending',
            'scheduled_at' => now()->addMinutes($config->delay_minutes),
        ]);
    }

    /**
     * 대기열에서 예정 시각이 지난 pending 건을 배분
     */
    public function processPendingQueue(): int
    {
        $pendingItems = DistributionQueue::where('status', 'pending')
            ->where('scheduled_at', '<=', now())
            ->get();

        $processed = 0;

        foreach ($pendingItems as $item) {
            if ($this->assignToNextAgent($item)) {
                $processed++;
            }
        }

        return $processed;
    }

    /**
     * 24시간 미확인 건 재배분
     */
    public function processTimeouts(): int
    {
        $timeoutItems = DistributionQueue::where('status', 'assigned')
            ->whereNull('viewed_at')
            ->get()
            ->filter(function ($item) {
                $config = DistributionConfig::where('branch_id', $item->branch_id)->first();
                if (!$config) return false;
                $timeoutAt = $item->assigned_at->addHours($config->timeout_hours);
                return now()->gte($timeoutAt);
            });

        $processed = 0;

        foreach ($timeoutItems as $item) {
            $oldAgentId = $item->assigned_agent_id;

            $item->update([
                'status' => 'pending',
                'assigned_agent_id' => null,
                'assigned_at' => null,
                'timeout_count' => $item->timeout_count + 1,
                'scheduled_at' => now(),
            ]);

            if ($this->assignToNextAgent($item, 'auto_timeout_reassign', "자동재배분(미확인): {$oldAgentId}")) {
                $processed++;
            }
        }

        return $processed;
    }

    /**
     * 다음 순서 설계사에게 배분
     */
    private function assignToNextAgent(DistributionQueue $item, string $assignmentType = 'auto_distribute', ?string $notePrefix = null): bool
    {
        return DB::transaction(function () use ($item, $assignmentType, $notePrefix) {
            $config = DistributionConfig::where('branch_id', $item->branch_id)
                ->lockForUpdate()
                ->first();

            if (!$config || !$config->is_active || !$config->current_list_id) {
                return false;
            }

            $updated = DistributionQueue::where('queue_id', $item->queue_id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if (!$updated) {
                return false;
            }

            $list = DistributionList::with('items')->find($config->current_list_id);
            if (!$list || $list->items->isEmpty()) {
                return false;
            }

            $totalAgents = $list->items->count();
            $startPosition = $config->current_position % $totalAgents;

            $listItem = null;
            $finalPosition = $startPosition;
            for ($i = 0; $i < $totalAgents; $i++) {
                $tryPosition = ($startPosition + $i) % $totalAgents;
                $candidate = $list->items->firstWhere('position', $tryPosition + 1);
                if ($candidate) {
                    $agent = Agent::where('agent_id', $candidate->agent_id)->first();
                    if ($agent && $agent->is_active) {
                        $listItem = $candidate;
                        $finalPosition = $tryPosition;
                        break;
                    }
                }
            }

            if (!$listItem) {
                Log::warning('자동배분 실패: 활성 설계사 없음', ['branch_id' => $item->branch_id]);
                return false;
            }

            $updated->update([
                'status' => 'assigned',
                'assigned_agent_id' => $listItem->agent_id,
                'assigned_at' => now(),
            ]);

            Customer::where('customer_id', $item->customer_id)
                ->update(['agent_id' => $listItem->agent_id]);

            $notes = $notePrefix
                ? "{$notePrefix} → {$listItem->agent_id} (순서: {$listItem->position})"
                : "자동배분 (순서: {$listItem->position})";

            CustomerAssignment::create([
                'customer_id' => $item->customer_id,
                'agent_id' => $listItem->agent_id,
                'assignment_type' => $assignmentType,
                'assignment_date' => now()->toDateString(),
                'notes' => $notes,
            ]);

            $config->update([
                'current_position' => ($finalPosition + 1) % $totalAgents,
            ]);

            $this->sendDistributionNotification($listItem->agent_id, $item->customer_id);

            return true;
        });
    }

    /**
     * 배분 알림 발송 (FCM + 앱 내 알림)
     */
    private function sendDistributionNotification(string $agentId, string $customerId): void
    {
        $customer = Customer::find($customerId);
        $customerName = $customer?->name ?? '새 고객';

        Notification::create([
            'receiver_id' => $agentId,
            'receiver_type' => 'AGENT',
            'sender_type' => 'SYSTEM',
            'notification_type' => 'distribution',
            'title' => '새 고객 배분',
            'content' => "새로운 고객이 배분되었습니다: {$customerName}",
            'is_read' => false,
            'sent_at' => now(),
            'created_at' => now(),
        ]);

        try {
            $this->fcmService->sendToUsers('AGENT', [$agentId], '새 고객 배분', "새로운 고객이 배분되었습니다: {$customerName}");
        } catch (\Exception $e) {
            Log::error('배분 알림 FCM 발송 실패', ['agent_id' => $agentId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * 설계사가 배분 확인 처리
     */
    public function confirmDistribution(string $agentId, int $queueId): bool
    {
        $item = DistributionQueue::where('queue_id', $queueId)
            ->where('assigned_agent_id', $agentId)
            ->where('status', 'assigned')
            ->first();

        if (!$item) {
            return false;
        }

        $item->update([
            'viewed_at' => now(),
            'status' => 'completed',
        ]);

        return true;
    }
}
