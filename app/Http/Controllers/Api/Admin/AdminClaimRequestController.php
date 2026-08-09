<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClaimRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminClaimRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ClaimRequest::with('files', 'assignedAgent', 'hospital');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // 정렬
        $sortField = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        if (in_array($sortField, ['created_at', 'status'])) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        }

        $requests = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $claimRequest = ClaimRequest::with('files', 'assignedAgent', 'hospital', 'linkedClaim')
            ->findOrFail($id);

        $matchedAgent = null;
        if ($claimRequest->phone) {
            $customer = \App\Models\Customer::with('agent:agent_id,name')
                ->where('phone', preg_replace('/\D/', '', $claimRequest->phone))
                ->first();
            if ($customer?->agent) {
                $matchedAgent = [
                    'agent_id' => $customer->agent->agent_id,
                    'name' => $customer->agent->name,
                ];
            }
        }

        $data = $claimRequest->toArray();
        $data['matched_agent'] = $matchedAgent;

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * 설계사 배정
     */
    public function assign(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => 'required|string|exists:agent,agent_id',
        ]);

        $claimRequest = ClaimRequest::findOrFail($id);
        $claimRequest->update([
            'assigned_agent_id' => $validated['agent_id'],
            'status' => 'assigned',
        ]);

        $claimRequest->load('assignedAgent');

        return response()->json([
            'success' => true,
            'data' => $claimRequest,
            'message' => '설계사가 배정되었습니다.',
        ]);
    }

    /**
     * 청구신청 대량 배정
     */
    public function bulkAssign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'request_ids' => 'required|array|min:1',
            'request_ids.*' => 'required|integer',
            'agent_id' => 'required|string|exists:agent,agent_id',
        ]);

        $agentId = $validated['agent_id'];
        $assignedCount = 0;

        foreach ($validated['request_ids'] as $requestId) {
            $claimRequest = ClaimRequest::where('request_id', $requestId)
                ->where('status', 'pending')
                ->first();

            if ($claimRequest) {
                $claimRequest->update([
                    'assigned_agent_id' => $agentId,
                    'status' => 'assigned',
                ]);
                $assignedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'data' => ['assigned_count' => $assignedCount],
            'message' => "{$assignedCount}건의 청구신청이 배정되었습니다.",
        ]);
    }

    /**
     * 상태 변경
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,assigned,completed,cancelled',
            'linked_claim_id' => 'nullable|integer',
        ]);

        $claimRequest = ClaimRequest::findOrFail($id);
        $claimRequest->update($validated);

        return response()->json([
            'success' => true,
            'data' => $claimRequest,
            'message' => '상태가 변경되었습니다.',
        ]);
    }
}
