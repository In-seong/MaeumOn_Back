<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsentTemplate;
use App\Models\HealthExternalAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 건강 데이터 조회 동의 관리
 * - health_checkup: NHIS 건강검진정보 조회 동의
 * - medical_info:  HIRA 내진료정보열람 동의
 */
class HealthConsentController extends Controller
{
    /**
     * 동의 상태 조회
     * GET /api/health/consent/status
     */
    public function status(Request $request): JsonResponse
    {
        $customerId = $request->user()->customer->customer_id;

        $account = HealthExternalAccount::where('customer_id', $customerId)->first();

        return response()->json([
            'success' => true,
            'data' => [
                'health_checkup_consented' => (bool) $account?->checkup_consent_at,
                'health_checkup_consented_at' => $account?->checkup_consent_at,
                'medical_info_consented' => (bool) $account?->medical_info_consent_at,
                'medical_info_consented_at' => $account?->medical_info_consent_at,
                'last_checkup_sync_at' => $account?->last_checkup_sync_at,
                'last_medical_info_sync_at' => $account?->last_medical_info_sync_at,
            ],
        ]);
    }

    /**
     * 동의 등록
     * POST /api/health/consent/agree
     * body: { type: 'health_checkup' | 'medical_info' }
     */
    public function agree(Request $request): JsonResponse
    {
        $customerId = $request->user()->customer->customer_id;

        $validated = $request->validate([
            'type' => 'required|string|in:health_checkup,medical_info',
        ]);

        $field = $validated['type'] === ConsentTemplate::TYPE_HEALTH_CHECKUP
            ? 'checkup_consent_at'
            : 'medical_info_consent_at';

        $account = HealthExternalAccount::firstOrCreate(
            ['customer_id' => $customerId],
            ['is_active' => true]
        );
        $account->{$field} = now();
        $account->save();

        return response()->json([
            'success' => true,
            'data' => $account,
            'message' => '동의가 저장되었습니다.',
        ]);
    }

    /**
     * 동의 철회
     * POST /api/health/consent/revoke
     * body: { type: 'health_checkup' | 'medical_info' }
     */
    public function revoke(Request $request): JsonResponse
    {
        $customerId = $request->user()->customer->customer_id;

        $validated = $request->validate([
            'type' => 'required|string|in:health_checkup,medical_info',
        ]);

        $field = $validated['type'] === ConsentTemplate::TYPE_HEALTH_CHECKUP
            ? 'checkup_consent_at'
            : 'medical_info_consent_at';

        $account = HealthExternalAccount::where('customer_id', $customerId)->first();
        if ($account) {
            $account->{$field} = null;
            $account->save();
        }

        return response()->json([
            'success' => true,
            'message' => '동의가 철회되었습니다.',
        ]);
    }
}
