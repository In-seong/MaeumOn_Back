<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FcmToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * FCM 토큰 등록/삭제 — Admin·Customer 공용.
 * Agent용은 기존 AgentFcmTokenController 유지(컨벤션 보존).
 */
class FcmTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token'   => 'required|string|max:500',
            'device_info' => 'nullable|string|max:500',
        ]);

        [$userType, $userId] = $this->resolveUser($request);

        FcmToken::updateOrCreate(
            [
                'user_type' => $userType,
                'user_id'   => $userId,
                'fcm_token' => $validated['fcm_token'],
            ],
            [
                'device_info' => $validated['device_info'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'FCM 토큰이 등록되었습니다.',
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => 'required|string',
        ]);

        [$userType, $userId] = $this->resolveUser($request);

        FcmToken::where('user_type', $userType)
            ->where('user_id', $userId)
            ->where('fcm_token', $validated['fcm_token'])
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'FCM 토큰이 삭제되었습니다.',
        ]);
    }

    /**
     * @return array{0:string,1:string}  [user_type, user_id]
     */
    private function resolveUser(Request $request): array
    {
        $account = $request->user();
        if ($account->isAdmin() && $account->admin) {
            return ['ADMIN', $account->admin->admin_id];
        }
        if ($account->isCustomer() && $account->customer) {
            return ['CUSTOMER', $account->customer->customer_id];
        }
        throw new HttpException(403, '잘못된 사용자 유형입니다.');
    }
}
