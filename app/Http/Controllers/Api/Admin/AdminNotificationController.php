<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Notification;
use App\Services\FcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    /**
     * 발송 이력 조회
     */
    public function index(Request $request): JsonResponse
    {
        $adminId = $request->user()->admin->admin_id;

        $query = Notification::where('sender_id', $adminId)
            ->where('sender_type', 'ADMIN')
            ->where('notification_type', 'ADMIN_MESSAGE');

        // 검색 (제목)
        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        $query->orderBy('created_at', 'desc');

        $perPage = min(max((int) $request->get('per_page', 15), 1), 100);

        // 동일 제목+내용+sent_at 기준으로 그룹핑하여 발송 건 단위로 조회
        $notifications = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ]);
    }

    /**
     * 알림 발송 (특정 설계사 또는 전체)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'required|string|max:2000',
            'target' => 'required|in:ALL,SELECTED',
            'agent_ids' => 'required_if:target,SELECTED|array',
            'agent_ids.*' => 'string|exists:agent,agent_id',
        ]);

        $adminId = $request->user()->admin->admin_id;

        if ($validated['target'] === 'ALL') {
            $agents = Agent::where('is_active', true)->get();
        } else {
            $agents = Agent::whereIn('agent_id', $validated['agent_ids'])
                ->where('is_active', true)
                ->get();
        }

        if ($agents->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => '발송 대상 설계사가 없습니다.',
            ], 422);
        }

        $sentAt = now();
        $count = 0;

        foreach ($agents as $agent) {
            Notification::create([
                'receiver_id' => $agent->agent_id,
                'receiver_type' => 'AGENT',
                'sender_id' => $adminId,
                'sender_type' => 'ADMIN',
                'notification_type' => 'ADMIN_MESSAGE',
                'title' => $validated['title'],
                'content' => $validated['content'],
                'is_read' => false,
                'sent_at' => $sentAt,
            ]);
            $count++;
        }

        // FCM 푸시 알림 발송
        $agentIds = $agents->pluck('agent_id')->toArray();
        try {
            $fcmService = new FcmService();
            $fcmResult = $fcmService->sendToUsers(
                'AGENT',
                $agentIds,
                $validated['title'],
                $validated['content']
            );
        } catch (\Exception $e) {
            $fcmResult = ['success' => 0, 'failure' => count($agentIds)];
            \Illuminate\Support\Facades\Log::error('FCM send failed', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'sent_count' => $count,
                'push_result' => $fcmResult,
            ],
            'message' => "{$count}명의 설계사에게 알림을 발송했습니다.",
        ], 201);
    }
}
