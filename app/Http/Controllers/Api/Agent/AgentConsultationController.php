<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentConsultationController extends Controller
{
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

        return response()->json([
            'success' => true,
            'data' => $consultations,
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
            ->findOrFail($id);

        $validated = $request->validate([
            'answer' => 'required|string',
        ]);

        $consultation->update([
            'consultation_status' => 'completed',
        ]);

        return response()->json([
            'success' => true,
            'data' => $consultation->load('customer:customer_id,name,phone'),
            'message' => '상담 답변이 등록되었습니다.',
        ]);
    }
}
