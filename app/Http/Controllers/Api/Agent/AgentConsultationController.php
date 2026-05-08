<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Services\ConsultationNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentConsultationController extends Controller
{
    public function __construct(private ConsultationNotifier $notifier)
    {
    }

    /**
     * 설계사에게 배정된 상담 목록
     */
    public function index(Request $request): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $query = Consultation::where('assignee_id', $agentId)
            ->where('assignee_type', 'AGENT')
            ->with(['customer:customer_id,name,phone']);

        // 상태 필터
        if ($request->has('status')) {
            $query->where('consultation_status', $request->status);
        }

        // 상담 유형 필터
        if ($request->has('consultation_type')) {
            $query->where('consultation_type', $request->consultation_type);
        }

        $consultations = $query->orderBy('created_at', 'desc')
            ->paginate(min(max((int) $request->get('per_page', 15), 1), 100));

        // 상태별 전체 건수
        $statusCounts = Consultation::where('assignee_id', $agentId)
            ->where('assignee_type', 'AGENT')
            ->selectRaw("
                COUNT(*) as total,
                SUM(consultation_status = 'pending') as pending,
                SUM(consultation_status = 'in_progress') as in_progress,
                SUM(consultation_status = 'completed') as completed
            ")
            ->first();

        return response()->json([
            'success' => true,
            'data' => $consultations,
            'status_counts' => [
                'all' => (int) $statusCounts->total,
                'pending' => (int) $statusCounts->pending,
                'in_progress' => (int) $statusCounts->in_progress,
                'completed' => (int) $statusCounts->completed,
            ],
        ]);
    }

    /**
     * 상담 상세
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $consultation = Consultation::where('assignee_id', $agentId)
            ->where('assignee_type', 'AGENT')
            ->with(['customer:customer_id,name,phone'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $consultation,
        ]);
    }

    /**
     * 상담 답변
     */
    public function answer(Request $request, int $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $consultation = Consultation::where('assignee_id', $agentId)
            ->where('assignee_type', 'AGENT')
            ->with('customer')
            ->findOrFail($id);

        $validated = $request->validate([
            'answer' => 'required|string',
        ]);

        $consultation->update([
            'consultation_answer' => $validated['answer'],
            'consultation_status' => 'completed',
        ]);

        if ($consultation->customer) {
            $this->notifier->onAnswered($consultation->refresh(), $consultation->customer, 'AGENT');
        }

        return response()->json([
            'success' => true,
            'data' => $consultation->load('customer:customer_id,name,phone'),
            'message' => '상담 답변이 등록되었습니다.',
        ]);
    }
}
