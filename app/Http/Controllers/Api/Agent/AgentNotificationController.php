<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentNotificationController extends Controller
{
    /**
     * List notifications for this agent.
     */
    public function index(Request $request): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $query = Notification::where('receiver_id', $agentId)
            ->where('receiver_type', 'AGENT');

        if ($request->has('is_read')) {
            $query->where('is_read', filter_var($request->get('is_read'), FILTER_VALIDATE_BOOLEAN));
        }

        $notifications = $query->orderBy('created_at', 'desc')
            ->paginate(min(max((int) $request->get('per_page', 15), 1), 100));

        $unreadCount = Notification::where('receiver_id', $agentId)
            ->where('receiver_type', 'AGENT')
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
            ],
            'message' => '알림 목록을 조회했습니다.',
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $notification = Notification::where('notification_id', $id)
            ->where('receiver_id', $agentId)
            ->where('receiver_type', 'AGENT')
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => '알림을 찾을 수 없습니다.',
            ], 404);
        }

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $notification->fresh(),
            'message' => '알림을 읽음 처리했습니다.',
        ]);
    }

    /**
     * Mark all unread notifications as read for this agent.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $updatedCount = Notification::where('receiver_id', $agentId)
            ->where('receiver_type', 'AGENT')
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'updated_count' => $updatedCount,
            ],
            'message' => "총 {$updatedCount}개의 알림을 읽음 처리했습니다.",
        ]);
    }
}
