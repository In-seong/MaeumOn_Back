<?php

namespace App\Services;

use App\Models\FcmToken;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FcmService
{
    private $messaging;

    public function __construct()
    {
        $credentialsPath = storage_path('firebase-credentials.json');

        if (!file_exists($credentialsPath)) {
            Log::error('Firebase credentials file not found: ' . $credentialsPath);
            return;
        }

        $factory = (new Factory)->withServiceAccount($credentialsPath);
        $this->messaging = $factory->createMessaging();
    }

    /**
     * 특정 사용자들에게 푸시 알림 발송
     */
    public function sendToUsers(string $userType, array $userIds, string $title, string $body): array
    {
        if (!$this->messaging) {
            Log::warning('FCM messaging not initialized');
            return ['success' => 0, 'failure' => count($userIds)];
        }

        $tokens = FcmToken::where('user_type', $userType)
            ->whereIn('user_id', $userIds)
            ->pluck('fcm_token')
            ->unique()
            ->values()
            ->toArray();

        if (empty($tokens)) {
            Log::info('FCM: No tokens found for users', ['user_type' => $userType, 'count' => count($userIds)]);
            return ['success' => 0, 'failure' => 0, 'no_token' => count($userIds)];
        }

        return $this->sendToTokens($tokens, $title, $body);
    }

    /**
     * FCM 토큰 목록에 푸시 발송
     */
    public function sendToTokens(array $tokens, string $title, string $body): array
    {
        if (!$this->messaging || empty($tokens)) {
            return ['success' => 0, 'failure' => count($tokens)];
        }

        $notification = Notification::create($title, $body);

        $message = CloudMessage::new()
            ->withNotification($notification)
            ->withData([
                'title' => $title,
                'body' => $body,
                'click_action' => 'OPEN_ACTIVITY',
            ]);

        try {
            $report = $this->messaging->sendMulticast($message, $tokens);

            $successCount = $report->successes()->count();
            $failureCount = $report->failures()->count();

            // 실패한 토큰 중 유효하지 않은 토큰 삭제
            if ($failureCount > 0) {
                $invalidTokens = [];
                foreach ($report->failures()->getItems() as $failure) {
                    $token = $failure->target()->value();
                    $error = $failure->error();

                    if ($error && in_array($error->getMessage(), [
                        'NOT_FOUND',
                        'UNREGISTERED',
                        'INVALID_ARGUMENT',
                    ])) {
                        $invalidTokens[] = $token;
                    }
                }

                if (!empty($invalidTokens)) {
                    FcmToken::whereIn('fcm_token', $invalidTokens)->delete();
                    Log::info('FCM: Removed invalid tokens', ['count' => count($invalidTokens)]);
                }
            }

            Log::info('FCM: Multicast result', [
                'success' => $successCount,
                'failure' => $failureCount,
                'total' => count($tokens),
            ]);

            return ['success' => $successCount, 'failure' => $failureCount];
        } catch (\Exception $e) {
            Log::error('FCM: Send failed', ['error' => $e->getMessage()]);
            return ['success' => 0, 'failure' => count($tokens)];
        }
    }
}
