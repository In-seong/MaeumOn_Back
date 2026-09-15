<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\CorporateInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentCorporateInquiryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $query = CorporateInquiry::where('agent_id', $agentId);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                  ->orWhere('ceo_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $inquiries = $query->orderByDesc('assigned_at')
            ->paginate(min(max((int) $request->get('per_page', 15), 1), 100));

        return response()->json([
            'success' => true,
            'data' => $inquiries,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $inquiry = CorporateInquiry::where('id', $id)
            ->where('agent_id', $agentId)
            ->first();

        if (!$inquiry) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => '기업 문의를 찾을 수 없습니다.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $inquiry,
        ]);
    }

    public function updateNotes(Request $request, int $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $inquiry = CorporateInquiry::where('id', $id)
            ->where('agent_id', $agentId)
            ->first();

        if (!$inquiry) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => '기업 문의를 찾을 수 없습니다.',
            ], 404);
        }

        $validated = $request->validate([
            'notes' => 'nullable|string|max:2000',
        ]);

        $inquiry->update($validated);

        return response()->json([
            'success' => true,
            'data' => $inquiry->fresh(),
            'message' => '메모가 저장되었습니다.',
        ]);
    }
}
