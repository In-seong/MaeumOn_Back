<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OtpService
{
    private const OTP_LENGTH = 6;
    private const OTP_TTL_SECONDS = 180; // 3분
    private const RATE_LIMIT_MAX = 5;
    private const RATE_LIMIT_WINDOW_SECONDS = 3600; // 1시간

    /**
     * OTP 생성 및 캐시 저장
     */
    public function generate(string $phone): array
    {
        // Rate limit 체크
        $rateLimitKey = "otp_rate:{$phone}";
        $attempts = (int) Cache::get($rateLimitKey, 0);

        if ($attempts >= self::RATE_LIMIT_MAX) {
            return [
                'success' => false,
                'message' => '인증번호 요청 한도를 초과했습니다. 1시간 후 다시 시도해주세요.',
            ];
        }

        // OTP 생성
        $otp = str_pad((string) random_int(0, 999999), self::OTP_LENGTH, '0', STR_PAD_LEFT);

        // 캐시에 저장 (3분 TTL)
        $cacheKey = "otp:{$phone}";
        Cache::put($cacheKey, $otp, self::OTP_TTL_SECONDS);

        // Rate limit 카운트 증가
        Cache::put($rateLimitKey, $attempts + 1, self::RATE_LIMIT_WINDOW_SECONDS);

        // SMS 발송 (개발환경: 로그 출력, 운영환경: 실제 SMS)
        $this->sendSms($phone, $otp);

        return [
            'success' => true,
            'message' => '인증번호가 발송되었습니다.',
            // 개발환경에서만 OTP 노출
            'debug_otp' => app()->environment('local') ? $otp : null,
        ];
    }

    /**
     * OTP 검증
     */
    public function verify(string $phone, string $otp): bool
    {
        $cacheKey = "otp:{$phone}";
        $storedOtp = Cache::get($cacheKey);

        if (!$storedOtp || $storedOtp !== $otp) {
            return false;
        }

        // 검증 성공 시 OTP 삭제 (1회용)
        Cache::forget($cacheKey);

        // 인증 완료 플래그 저장 (10분간 유효 - PIN 설정/로그인에 사용)
        Cache::put("otp_verified:{$phone}", true, 600);

        return true;
    }

    /**
     * OTP 인증 완료 여부 확인
     */
    public function isVerified(string $phone): bool
    {
        return (bool) Cache::get("otp_verified:{$phone}", false);
    }

    /**
     * OTP 인증 완료 플래그 소모
     */
    public function consumeVerification(string $phone): void
    {
        Cache::forget("otp_verified:{$phone}");
    }

    /**
     * SMS 발송
     */
    private function sendSms(string $phone, string $otp): void
    {
        if (app()->environment('local', 'testing')) {
            Log::info("[OTP] {$phone} => {$otp}");
            return;
        }

        // TODO: 운영환경 SMS API 연동 (예: NHN Cloud, CoolSMS 등)
        Log::warning("[OTP] Production SMS not configured. Phone: {$phone}");
    }
}
