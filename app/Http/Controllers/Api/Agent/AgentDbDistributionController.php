<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\ClaimRequest;
use App\Models\Customer;
use App\Models\CustomerAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentDbDistributionController extends Controller
{
    /**
     * DB 배분(고객 배정) 목록 조회
     */
    public function index(Request $request): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $query = CustomerAssignment::where('agent_id', $agentId);

        $assignments = $query->with('customer:customer_id,name,phone')
            ->orderBy('created_at', 'desc')
            ->paginate(min(max((int) $request->get('per_page', 15), 1), 100));

        return response()->json([
            'success' => true,
            'data' => $assignments,
            'message' => 'DB 배분 목록을 조회했습니다.',
        ]);
    }

    /**
     * DB 배분 상세 조회
     */
    public function show(Request $request, $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $assignment = CustomerAssignment::where('assignment_id', $id)
            ->where('agent_id', $agentId)
            ->with('customer')
            ->first();

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => '배정 정보를 찾을 수 없습니다.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $assignment,
            'message' => '배정 상세 정보를 조회했습니다.',
        ]);
    }

    /**
     * 청구 배정 목록 조회 (관리자가 배정한 청구 신청)
     */
    public function claimAssignments(Request $request): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $assignments = ClaimRequest::where('assigned_agent_id', $agentId)
            ->with(['hospital:hospital_id,hospital_name', 'files'])
            ->orderBy('created_at', 'desc')
            ->get();

        $agentPhones = Customer::where('agent_id', $agentId)
            ->where('is_active', true)
            ->pluck('phone')
            ->map(fn ($p) => preg_replace('/\D/', '', $p ?? ''))
            ->filter()
            ->toArray();

        $assignments->each(function ($a) use ($agentPhones) {
            $phone = preg_replace('/\D/', '', $a->phone ?? '');
            $a->is_registered = in_array($phone, $agentPhones);
        });

        return response()->json([
            'success' => true,
            'data' => $assignments,
            'message' => '청구 배정 목록을 조회했습니다.',
        ]);
    }

    /**
     * DB 배분 처리
     */
    public function process(Request $request, $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $assignment = CustomerAssignment::where('assignment_id', $id)
            ->where('agent_id', $agentId)
            ->first();

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => '배정 정보를 찾을 수 없습니다.',
            ], 404);
        }

        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $assignment->update($validated);

        return response()->json([
            'success' => true,
            'data' => $assignment->fresh()->load('customer'),
            'message' => '배정을 처리했습니다.',
        ]);
    }
}
