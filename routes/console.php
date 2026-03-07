<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 팩스 발송 결과 확인 (매분 실행)
Schedule::command('fax:check-results')->everyMinute();

// 팩스 발송 완료 후 SendDoc 파일 정리 (5분마다)
Schedule::command('fax:cleanup-files')->everyFiveMinutes();

// 캘린더 리마인더 자동 생성 및 알림 발송 (매일 08:00)
Schedule::command('calendar:generate-reminders')->dailyAt('08:00');
