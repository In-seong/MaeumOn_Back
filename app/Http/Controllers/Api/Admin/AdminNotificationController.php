<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\BranchFilterable;
use App\Models\Agent;
use App\Models\Notification;
use App\Services\FcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    use BranchFilterable;
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

        $branchId = $this->resolveBranchId($request);

        if ($validated['target'] === 'ALL') {
            $agentQuery = Agent::where('is_active', true);
            $this->applyAgentBranchFilter($agentQuery, $branchId);
            $agents = $agentQuery->get();
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

    public function received(Request $request): JsonResponse
    {
        $adminId = $request->user()->admin->admin_id;

        $query = Notification::where('receiver_id', $adminId)
            ->where('receiver_type', 'ADMIN');

        if ($request->has('is_read')) {
            $query->where('is_read', filter_var($request->get('is_read'), FILTER_VALIDATE_BOOLEAN));
        }

        $notifications = $query->orderBy('created_at', 'desc')
            ->paginate(min(max((int) $request->get('per_page', 15), 1), 100));

        $unreadCount = Notification::where('receiver_id', $adminId)
            ->where('receiver_type', 'ADMIN')
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    public function markAsRead(Request $request, $id): JsonResponse
    {
        $adminId = $request->user()->admin->admin_id;

        $notification = Notification::where('notification_id', $id)
            ->where('receiver_id', $adminId)
            ->where('receiver_type', 'ADMIN')
            ->first();

        if (!$notification) {
            return response()->json(['success' => false, 'message' => '알림을 찾을 수 없습니다.'], 404);
        }

        $notification->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['success' => true, 'data' => $notification->fresh()]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $adminId = $request->user()->admin->admin_id;

        $updatedCount = Notification::where('receiver_id', $adminId)
            ->where('receiver_type', 'ADMIN')
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json([
            'success' => true,
            'data' => ['updated_count' => $updatedCount],
        ]);
    }
}
