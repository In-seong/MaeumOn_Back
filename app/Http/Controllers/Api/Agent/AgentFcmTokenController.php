<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\FcmToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentFcmTokenController extends Controller
{
    /**
     * FCM 토큰 등록/갱신
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => 'required|string|max:500',
            'device_info' => 'nullable|string|max:500',
        ]);

        $agentId = $request->user()->agent->agent_id;

        // 같은 토큰이 이미 존재하면 업데이트, 없으면 생성
        FcmToken::updateOrCreate(
            [
                'user_type' => 'AGENT',
                'user_id' => $agentId,
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

    /**
     * FCM 토큰 삭제 (로그아웃 시)
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => 'required|string',
        ]);

        $agentId = $request->user()->agent->agent_id;

        FcmToken::where('user_type', 'AGENT')
            ->where('user_id', $agentId)
            ->where('fcm_token', $validated['fcm_token'])
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'FCM 토큰이 삭제되었습니다.',
        ]);
    }
}
