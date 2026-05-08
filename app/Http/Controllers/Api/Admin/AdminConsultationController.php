<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Consultation;
use App\Services\ConsultationNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminConsultationController extends Controller
{
    public function __construct(private ConsultationNotifier $notifier)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Consultation::with(['customer']);

        if ($request->filled('consultation_status')) {
            $query->where('consultation_status', $request->consultation_status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('consultation_content', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('consultation_type')) {
            $query->where('consultation_type', $request->consultation_type);
        }

        $perPage = (int) $request->input('per_page', 15);

        return response()->json([
            'success' => true,
            'data'    => $query->orderBy('created_at', 'desc')->paginate($perPage),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $consultation = Consultation::with(['customer'])->findOrFail($id);

        // 배정된 설계사 정보(있을 때만 포함) — 별도 조회로 단순 처리
        $agent = null;
        if ($consultation->assignee_type === 'AGENT' && $consultation->assignee_id) {
            $agent = Agent::where('agent_id', $consultation->assignee_id)
                ->select('agent_id', 'name', 'phone', 'email', 'office_location')
                ->first();
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'consultation' => $consultation,
                'agent'        => $agent,
            ],
        ]);
    }

    /**
     * 관리자가 상담에 답변 (시나리오 A-3, A-4).
     * 답변 등록 → 고객에게 푸시 + (담당설계사 있으면 그에게도 푸시).
     */
    public function answer(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'answer' => 'required|string',
        ]);

        $consultation = Consultation::with('customer')->findOrFail($id);

        $consultation->update([
            'consultation_answer' => $validated['answer'],
            'consultation_status' => 'completed',
        ]);

        if ($consultation->customer) {
            $this->notifier->onAnswered($consultation->refresh(), $consultation->customer, 'ADMIN');
        }

        return response()->json([
            'success' => true,
            'data'    => $consultation,
            'message' => '답변이 등록되었습니다.',
        ]);
    }

    /**
     * 관리자가 상담에 담당 설계사 배정 (시나리오 A-1).
     * 배정 → 그 설계사 + 고객 모두에게 푸시.
     */
    public function assign(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => 'required|string|size:8|exists:agent,agent_id',
        ]);

        $consultation = Consultation::with('customer')->findOrFail($id);

        $consultation->update([
            'assignee_id'         => $validated['agent_id'],
            'assignee_type'       => 'AGENT',
            'consultation_status' => $consultation->consultation_status === 'pending'
                ? 'in_progress'
                : $consultation->consultation_status,
        ]);

        if ($consultation->customer) {
            $this->notifier->onAssigned($consultation->refresh(), $consultation->customer);
        }

        return response()->json([
            'success' => true,
            'data'    => $consultation,
            'message' => '담당 설계사가 배정되었습니다.',
        ]);
    }
}
