<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\BranchFilterable;
use App\Models\Notice;
use App\Models\Notification;
use App\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNoticeController extends Controller
{
    use BranchFilterable;

    /**
     * 공지사항 목록 조회
     */
    public function index(Request $request): JsonResponse
    {
        $query = Notice::with('author:admin_id,name');

        $branchId = $this->resolveBranchId($request);
        if ($branchId !== null) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }

        // 공지 유형 필터
        if ($request->has('notice_type') && $request->notice_type) {
            $query->where('notice_type', $request->notice_type);
        }

        // 고정 여부 필터
        if ($request->has('is_pinned')) {
            $query->where('is_pinned', filter_var($request->is_pinned, FILTER_VALIDATE_BOOLEAN));
        }

        // 고정글 우선, 최신순 정렬
        $query->orderBy('is_pinned', 'desc')
              ->orderBy('created_at', 'desc');

        $perPage = min(max((int) $request->get('per_page', 15), 1), 100);
        $notices = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $notices,
        ]);
    }

    /**
     * 공지사항 상세 조회
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $notice = Notice::with('author:admin_id,name')->findOrFail($id);

        // 조회수 증가
        $notice->increment('view_count');

        return response()->json([
            'success' => true,
            'data' => $notice,
        ]);
    }

    /**
     * 공지사항 등록
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'required|string',
            'notice_type' => 'nullable|string|max:50',
            'is_pinned' => 'boolean',
            'display_start_date' => 'nullable|date',
            'display_end_date' => 'nullable|date',
        ]);

        $admin = $request->user()->admin;
        $adminId = $admin->admin_id;

        $branchId = ($admin->admin_role === 'BRANCH' && $admin->branch_id)
            ? $admin->branch_id
            : null;

        $notice = Notice::create(array_merge($validated, [
            'author_id' => $adminId,
            'branch_id' => $branchId,
            'is_pinned' => $validated['is_pinned'] ?? false,
            'view_count' => 0,
        ]));

        $agentQuery = Agent::where('is_active', true);
        if ($branchId) {
            $this->applyAgentBranchFilter($agentQuery, $branchId);
        }
        $agents = $agentQuery->get();

        foreach ($agents as $agent) {
            Notification::create([
                'receiver_id' => $agent->agent_id,
                'receiver_type' => 'AGENT',
                'sender_id' => $adminId,
                'sender_type' => 'ADMIN',
                'notification_type' => 'NOTICE',
                'title' => '새 공지사항: ' . $notice->title,
                'content' => mb_substr($notice->content, 0, 100),
                'is_read' => false,
                'sent_at' => now(),
            ]);
        }

        $notice->load('author:admin_id,name');

        return response()->json([
            'success' => true,
            'data' => $notice,
            'message' => '공지사항이 등록되었습니다.',
        ], 201);
    }

    /**
     * 공지사항 수정
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $notice = Notice::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:200',
            'content' => 'sometimes|required|string',
            'notice_type' => 'sometimes|nullable|string|max:50',
            'is_pinned' => 'sometimes|boolean',
            'display_start_date' => 'sometimes|nullable|date',
            'display_end_date' => 'sometimes|nullable|date',
        ]);

        $notice->update($validated);

        $notice->load('author:admin_id,name');

        return response()->json([
            'success' => true,
            'data' => $notice,
            'message' => '공지사항이 수정되었습니다.',
        ]);
    }

    /**
     * 공지사항 삭제
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $notice = Notice::findOrFail($id);

        $notice->delete();

        return response()->json([
            'success' => true,
            'message' => '공지사항이 삭제되었습니다.',
        ]);
    }
}
