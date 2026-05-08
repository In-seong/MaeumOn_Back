<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Consultation;
use App\Models\Customer;
use App\Models\Notification;

/**
 * 상담 요청 흐름 전반의 푸시/알림 적재를 담당.
 * 시나리오:
 *  - onRequested: 고객이 상담 요청 → 담당설계사 또는 관리자 전체
 *  - onAssigned : 관리자가 담당설계사 배정 → 그 설계사 + 고객
 *  - onAnswered : 답변 등록 → 고객 (+ 답변자가 관리자면 담당설계사에도)
 */
class ConsultationNotifier
{
    public function __construct(private FcmService $fcm) {}

    public function onRequested(Consultation $c, Customer $customer): void
    {
        $title = '새 상담 요청';
        $body  = sprintf('%s 고객의 %s 요청', $customer->name, $c->consultation_type);

        if ($c->assignee_type === 'AGENT' && $c->assignee_id) {
            $this->push('AGENT', [$c->assignee_id], $title, $body, 'consultation_request');
            return;
        }
        $adminIds = Admin::where('is_active', 1)->pluck('admin_id')->all();
        $this->push('ADMIN', $adminIds, $title, $body, 'consultation_request');
    }

    public function onAssigned(Consultation $c, Customer $customer): void
    {
        if ($c->assignee_type !== 'AGENT' || !$c->assignee_id) {
            return;
        }

        $agentTitle = '상담 담당 배정';
        $agentBody  = sprintf('%s 고객의 %s 상담이 배정되었습니다.', $customer->name, $c->consultation_type);
        $this->push('AGENT', [$c->assignee_id], $agentTitle, $agentBody, 'consultation_assigned');

        $customerTitle = '담당 설계사 배정';
        $customerBody  = sprintf('요청하신 %s에 담당 설계사가 배정되었습니다.', $c->consultation_type);
        $this->push('CUSTOMER', [$customer->customer_id], $customerTitle, $customerBody, 'consultation_assigned');
    }

    public function onAnswered(Consultation $c, Customer $customer, string $answererType): void
    {
        $title = '상담 답변 도착';
        $body  = sprintf('요청하신 %s에 답변이 등록되었습니다.', $c->consultation_type);

        $this->push('CUSTOMER', [$customer->customer_id], $title, $body, 'consultation_answered');

        // 관리자가 답변했고 담당설계사가 배정되어 있으면 설계사에게도 통보
        if ($answererType === 'ADMIN' && $c->assignee_type === 'AGENT' && $c->assignee_id) {
            $agentBody = sprintf('%s 고객 상담에 관리자 답변이 등록되었습니다.', $customer->name);
            $this->push('AGENT', [$c->assignee_id], $title, $agentBody, 'consultation_answered');
        }
    }

    /**
     * notification 테이블 적재 + FCM 푸시.
     * sender_type ENUM이 ('AGENT','ADMIN','SYSTEM')만 허용 → 모든 시스템 알림은 SYSTEM.
     */
    private function push(string $userType, array $userIds, string $title, string $body, string $type): void
    {
        if (empty($userIds)) {
            return;
        }
        $now = now();
        $rows = array_map(fn($id) => [
            'receiver_id'       => $id,
            'receiver_type'     => $userType,
            'sender_id'         => null,
            'sender_type'       => 'SYSTEM',
            'notification_type' => $type,
            'title'             => $title,
            'content'           => $body,
            'is_read'           => false,
            'sent_at'           => $now,
            'created_at'        => $now,
        ], $userIds);
        Notification::insert($rows);
        $this->fcm->sendToUsers($userType, $userIds, $title, $body);
    }
}
