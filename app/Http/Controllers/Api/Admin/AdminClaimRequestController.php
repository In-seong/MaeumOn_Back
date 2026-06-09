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

        $requests = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $claimRequest = ClaimRequest::with('files', 'assignedAgent', 'linkedClaim')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $claimRequest,
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
