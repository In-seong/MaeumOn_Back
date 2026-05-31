<?php

namespace App\Models\Traits;

use Carbon\Carbon;

trait HasScheduleConfig
{
    private const DEFAULT_INTERVAL = 30;

    private const DAY_MAP = [
        0 => 'sun', 1 => 'mon', 2 => 'tue', 3 => 'wed',
        4 => 'thu', 5 => 'fri', 6 => 'sat',
    ];

    private const DEFAULT_WEEKLY = [
        'mon' => ['slots' => [['start' => '09:00', 'end' => '17:00']]],
        'tue' => ['slots' => [['start' => '09:00', 'end' => '17:00']]],
        'wed' => ['slots' => [['start' => '09:00', 'end' => '17:00']]],
        'thu' => ['slots' => [['start' => '09:00', 'end' => '17:00']]],
        'fri' => ['slots' => [['start' => '09:00', 'end' => '17:00']]],
        'sat' => null,
        'sun' => null,
    ];

    public function generateTimeSlots(string $date): array
    {
        $config = $this->schedule_config;
        $carbon = Carbon::parse($date);
        $dayKey = self::DAY_MAP[$carbon->dayOfWeek];
        $defaultInterval = self::DEFAULT_INTERVAL;

        // schedule_config가 NULL이면 기본값 사용
        if ($config === null) {
            $dayConfig = self::DEFAULT_WEEKLY[$dayKey] ?? null;
            if ($dayConfig === null) {
                return [];
            }
            return $this->buildSlots($dayConfig['slots'], $defaultInterval);
        }

        $defaultInterval = $config['default_interval'] ?? self::DEFAULT_INTERVAL;

        // 1. blocked_dates 확인
        $blockedDates = $config['blocked_dates'] ?? [];
        if (in_array($date, $blockedDates)) {
            return [];
        }

        // 2. custom_dates 확인 (특정 날짜 우선)
        $customDates = $config['custom_dates'] ?? [];
        if (isset($customDates[$date])) {
            $custom = $customDates[$date];
            $interval = $custom['interval'] ?? $defaultInterval;
            return $this->buildSlots($custom['slots'] ?? [], $interval);
        }

        // 3. weekly 설정 확인
        $weekly = $config['weekly'] ?? self::DEFAULT_WEEKLY;
        if (!isset($weekly[$dayKey])) {
            // weekly에 해당 요일 키가 없으면 기본값 사용
            $dayConfig = self::DEFAULT_WEEKLY[$dayKey] ?? null;
            if ($dayConfig === null) {
                return [];
            }
            return $this->buildSlots($dayConfig['slots'], $defaultInterval);
        }

        $dayConfig = $weekly[$dayKey];
        if ($dayConfig === null) {
            return []; // 휴무
        }

        $dayInterval = $dayConfig['interval'] ?? $defaultInterval;
        return $this->buildSlots($dayConfig['slots'] ?? [], $dayInterval);
    }

    private function buildSlots(array $slotRanges, int $fallbackInterval): array
    {
        $times = [];

        foreach ($slotRanges as $range) {
            $start = $range['start'] ?? null;
            $end = $range['end'] ?? null;
            if (!$start || !$end) {
                continue;
            }

            $interval = $range['interval'] ?? $fallbackInterval;
            $current = Carbon::createFromFormat('H:i', $start);
            $endTime = Carbon::createFromFormat('H:i', $end);

            while ($current->lt($endTime)) {
                $times[] = $current->format('H:i');
                $current->addMinutes($interval);
            }
        }

        return array_values(array_unique($times));
    }

    public static function scheduleConfigValidationRules(): array
    {
        return [
            'schedule_config' => 'nullable|array',
            'schedule_config.default_interval' => 'sometimes|integer|min:5|max:120',
            'schedule_config.weekly' => 'sometimes|array',
            'schedule_config.weekly.*' => 'nullable|array',
            'schedule_config.weekly.*.slots' => 'sometimes|array',
            'schedule_config.weekly.*.slots.*.start' => 'required_with:schedule_config.weekly.*.slots|string',
            'schedule_config.weekly.*.slots.*.end' => 'required_with:schedule_config.weekly.*.slots|string',
            'schedule_config.weekly.*.slots.*.interval' => 'sometimes|integer|min:5|max:120',
            'schedule_config.weekly.*.interval' => 'sometimes|integer|min:5|max:120',
            'schedule_config.blocked_dates' => 'sometimes|array',
            'schedule_config.blocked_dates.*' => 'date',
            'schedule_config.custom_dates' => 'sometimes|array',
        ];
    }
}
