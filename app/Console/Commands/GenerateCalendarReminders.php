<?php

namespace App\Console\Commands;

use App\Models\CalendarEvent;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\Reminder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateCalendarReminders extends Command
{
    protected $signature = 'calendar:generate-reminders';
    protected $description = '고객 생일/계약 만기 자동 이벤트 생성 및 리마인더 알림 발송';

    public function handle(): int
    {
        $this->generateBirthdayEvents();
        $this->generateContractExpiryEvents();
        $this->sendPendingReminders();

        return self::SUCCESS;
    }

    /**
     * 고객 생일 이벤트 자동 생성 (향후 90일 이내)
     */
    private function generateBirthdayEvents(): void
    {
        $today = Carbon::today();

        // birth_date가 있고 활성 상태인 고객 중, 담당 설계사가 있는 고객
        $customers = Customer::whereNotNull('birth_date')
            ->whereNotNull('agent_id')
            ->where('is_active', true)
            ->get();

        $created = 0;

        foreach ($customers as $customer) {
            // 올해 생일 계산
            $birthday = Carbon::parse($customer->birth_date)->setYear($today->year);
            // 올해 생일이 이미 지났으면 내년
            if ($birthday->lt($today)) {
                $birthday->addYear();
            }

            // 90일 이내만
            if ($birthday->diffInDays($today) > 90) {
                continue;
            }

            $dateStr = $birthday->toDateString();

            // 이미 존재하는지 확인 (source=system, event_type=birthday, customer_id, event_date)
            $exists = CalendarEvent::where('agent_id', $customer->agent_id)
                ->where('customer_id', $customer->customer_id)
                ->where('event_type', CalendarEvent::TYPE_BIRTHDAY)
                ->where('event_date', $dateStr)
                ->where('source', CalendarEvent::SOURCE_SYSTEM)
                ->exists();

            if ($exists) {
                continue;
            }

            $event = CalendarEvent::create([
                'agent_id' => $customer->agent_id,
                'customer_id' => $customer->customer_id,
                'event_type' => CalendarEvent::TYPE_BIRTHDAY,
                'title' => "{$customer->name} 고객 생일",
                'event_date' => $dateStr,
                'is_all_day' => true,
                'is_recurring' => true,
                'source' => CalendarEvent::SOURCE_SYSTEM,
            ]);

            // 기본 리마인더: D-3, D-1, D-day
            foreach ([3, 1, 0] as $days) {
                Reminder::create([
                    'event_id' => $event->event_id,
                    'agent_id' => $customer->agent_id,
                    'remind_before_days' => $days,
                ]);
            }

            $created++;
        }

        if ($created > 0) {
            $this->info("생일 이벤트 {$created}건 생성");
        }
    }

    /**
     * 계약 만기 이벤트 자동 생성 (향후 90일 이내)
     */
    private function generateContractExpiryEvents(): void
    {
        $today = Carbon::today();
        $until = Carbon::today()->addDays(90);

        $contracts = Contract::whereNotNull('expiration_date')
            ->whereBetween('expiration_date', [$today->toDateString(), $until->toDateString()])
            ->where('contract_status', 'active')
            ->with('customer:customer_id,name,agent_id')
            ->get();

        $created = 0;

        foreach ($contracts as $contract) {
            $customer = $contract->customer;
            if (!$customer || !$customer->agent_id) {
                continue;
            }

            $dateStr = Carbon::parse($contract->expiration_date)->toDateString();

            $exists = CalendarEvent::where('agent_id', $customer->agent_id)
                ->where('contract_id', $contract->contract_id)
                ->where('event_type', CalendarEvent::TYPE_CONTRACT_EXPIRY)
                ->where('event_date', $dateStr)
                ->where('source', CalendarEvent::SOURCE_SYSTEM)
                ->exists();

            if ($exists) {
                continue;
            }

            $productName = $contract->insurance_product ?? '보험';

            $event = CalendarEvent::create([
                'agent_id' => $customer->agent_id,
                'customer_id' => $customer->customer_id,
                'contract_id' => $contract->contract_id,
                'event_type' => CalendarEvent::TYPE_CONTRACT_EXPIRY,
                'title' => "{$customer->name} 고객 {$productName} 만기",
                'event_date' => $dateStr,
                'is_all_day' => true,
                'source' => CalendarEvent::SOURCE_SYSTEM,
            ]);

            // 기본 리마인더: D-30, D-7, D-3, D-1
            foreach ([30, 7, 3, 1] as $days) {
                Reminder::create([
                    'event_id' => $event->event_id,
                    'agent_id' => $customer->agent_id,
                    'remind_before_days' => $days,
                ]);
            }

            $created++;
        }

        if ($created > 0) {
            $this->info("계약 만기 이벤트 {$created}건 생성");
        }
    }

    /**
     * 미발송 리마인더 → notification 발송
     */
    private function sendPendingReminders(): void
    {
        $today = Carbon::today();

        $reminders = Reminder::where('is_sent', false)
            ->with('event.customer:customer_id,name')
            ->get();

        $sent = 0;

        foreach ($reminders as $reminder) {
            $event = $reminder->event;
            if (!$event) {
                continue;
            }

            $eventDate = Carbon::parse($event->event_date);
            $remindDate = $eventDate->copy()->subDays($reminder->remind_before_days);

            // 오늘이 알림 발송일이면 발송
            if (!$remindDate->eq($today)) {
                continue;
            }

            $daysLeft = $reminder->remind_before_days;
            $timeLabel = $daysLeft === 0 ? '오늘' : "{$daysLeft}일 후";

            Notification::create([
                'receiver_id' => $reminder->agent_id,
                'receiver_type' => 'AGENT',
                'sender_id' => null,
                'sender_type' => 'SYSTEM',
                'notification_type' => 'schedule',
                'title' => $event->title,
                'content' => "{$event->title} ({$timeLabel})",
                'is_read' => false,
                'sent_at' => now(),
            ]);

            $reminder->update([
                'is_sent' => true,
                'sent_at' => now(),
            ]);

            $sent++;
        }

        if ($sent > 0) {
            $this->info("리마인더 알림 {$sent}건 발송");
        }
    }
}
