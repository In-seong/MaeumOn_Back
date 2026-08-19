<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\BranchFilterable;
use App\Models\Agent;
use App\Models\ClaimRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminClaimRequestController extends Controller
{
    use BranchFilterable;
    /**
     * 관리자가 직접 청구 신청 등록
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'phone' => 'required|string|max:20',
            'hospital_id' => 'nullable|integer|exists:partner_hospital,hospital_id',
            'memo' => 'nullable|string|max:2000',
            'files' => 'nullable|array|max:10',
            'files.*' => 'file|max:10240',
            'agent_id' => 'nullable|string|exists:agent,agent_id',
            'source_type' => 'nullable|in:resident,distribution',
        ]);

        $agentId = $validated['agent_id'] ?? null;

        $claimRequest = ClaimRequest::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'hospital_id' => $validated['hospital_id'] ?? null,
            'memo' => $validated['memo'] ?? null,
            'status' => $agentId ? 'assigned' : 'pending',
            'source_type' => $validated['source_type'] ?? 'resident',
            'assigned_agent_id' => $agentId,
        ]);

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = Storage::disk('s3')->put(
                    'claim-requests/' . $claimRequest->request_id,
                    $file
                );

                $claimRequest->files()->create([
                    'file_url' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        $claimRequest->load('files', 'assignedAgent', 'hospital');

        return response()->json([
            'success' => true,
            'data' => $claimRequest,
            'message' => '청구 신청이 등록되었습니다.',
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $query = ClaimRequest::with('files', 'assignedAgent', 'hospital');

        $branchId = $this->resolveBranchId($request);
        $this->applyAgentBranchFilter($query, $branchId, 'assignedAgent.branches');

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
