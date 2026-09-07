<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\HospitalReservation;
use App\Models\Notification;

class ReservationNotifier
{
    public function __construct(private FcmService $fcm) {}

    public function onStatusChanged(HospitalReservation $reservation, string $newStatus): void
    {
        $statusLabels = [
            'confirmed' => '확정',
            'cancelled' => '취소',
            'completed' => '완료',
        ];

        $label = $statusLabels[$newStatus] ?? null;
        if (!$label) {
            return;
        }

        $phone = preg_replace('/\D/', '', $reservation->patient_phone ?? '');
        if (!$phone) {
            return;
        }

        $customers = Customer::whereRaw("REPLACE(REPLACE(phone, '-', ''), ' ', '') = ?", [$phone])
            ->where('is_active', true)
            ->pluck('customer_id')
            ->all();

        if (empty($customers)) {
            return;
        }

        $facilityName = $reservation->hospital?->name
            ?? $reservation->healthCenter?->center_name
            ?? '병원';

        $date = $reservation->reservation_date?->format('Y-m-d') ?? '';
        $time = $reservation->reservation_time ?? '';

        $title = "예약 {$label}";
        $body = "{$facilityName} {$date} {$time} 예약이 {$label}되었습니다.";

        $now = now();
        $rows = array_map(fn($id) => [
            'receiver_id'       => $id,
            'receiver_type'     => 'CUSTOMER',
            'sender_id'         => null,
            'sender_type'       => 'SYSTEM',
            'notification_type' => 'reservation_status',
            'title'             => $title,
            'content'           => $body,
            'is_read'           => false,
            'sent_at'           => $now,
            'created_at'        => $now,
        ], $customers);

        Notification::insert($rows);
        $this->fcm->sendToUsers('CUSTOMER', $customers, $title, $body);
    }
}
