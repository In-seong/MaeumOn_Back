<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentInquiry;
use App\Models\Notification;
use App\Services\FcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminInquiryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AgentInquiry::with('agent:agent_id,name')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('agent', function ($aq) use ($search) {
                      $aq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $inquiries = $query->paginate(
            min(max((int) $request->get('per_page', 20), 1), 100)
        );

        return response()->json([
            'success' => true,
            'data' => $inquiries,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $inquiry = AgentInquiry::with([
            'agent:agent_id,name,phone',
            'answeredByAdmin:admin_id,name',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $inquiry,
        ]);
    }

    public function answer(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'answer' => 'required|string|max:5000',
        ]);

        $adminId = $request->user()->admin->admin_id;

        $inquiry = AgentInquiry::findOrFail($id);
        $inquiry->answer = $validated['answer'];
        $inquiry->answered_by = $adminId;
        $inquiry->answered_at = now();
        $inquiry->status = 'answered';
        $inquiry->save();

        try {
            Notification::create([
                'receiver_id' => $inquiry->agent_id,
                'receiver_type' => 'AGENT',
                'sender_id' => $adminId,
                'sender_type' => 'ADMIN',
                'notification_type' => 'INQUIRY_ANSWER',
                'title' => '문의 답변',
                'content' => "'{$inquiry->title}' 문의에 답변이 등록되었습니다.",
                'is_read' => false,
                'sent_at' => now(),
                'reference_id' => $inquiry->inquiry_id,
                'reference_type' => 'agent_inquiry',
            ]);

            app(FcmService::class)->sendToUsers(
                'AGENT',
                [$inquiry->agent_id],
                '문의 답변',
                "'{$inquiry->title}' 문의에 답변이 등록되었습니다.",
                [
                    'notification_type' => 'INQUIRY_ANSWER',
                    'reference_type' => 'agent_inquiry',
                    'reference_id' => (string) $inquiry->inquiry_id,
                ]
            );
        } catch (\Exception $e) {
            Log::error('문의 답변 알림 발송 실패', [
                'inquiry_id' => $inquiry->inquiry_id,
                'error' => $e->getMessage(),
            ]);
        }

        $inquiry->load('answeredByAdmin:admin_id,name');

        return response()->json([
            'success' => true,
            'data' => $inquiry,
            'message' => '답변이 등록되었습니다.',
        ]);
    }
}
