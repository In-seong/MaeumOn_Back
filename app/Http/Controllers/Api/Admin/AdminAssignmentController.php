<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\BranchFilterable;
use App\Models\ClaimRequest;
use App\Models\Customer;
use App\Models\Agent;
use App\Models\CustomerAssignment;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAssignmentController extends Controller
{
    use BranchFilterable;

    /**
     * DB 배분 이력 목록 조회
     */
    public function index(Request $request): JsonResponse
    {
        $query = CustomerAssignment::with([
            'customer:customer_id,name,phone',
            'agent:agent_id,name,phone',
        ]);

        $branchId = $this->resolveBranchId($request);
        $this->applyAgentBranchFilter($query, $branchId, 'agent.branches');

        // 검색 (고객 이름)
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('customer', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        // 정렬
        $sortField = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        if (in_array($sortField, ['created_at', 'assignment_date'])) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        }

        $perPage = min(max((int) $request->get('per_page', 15), 1), 100);
        $assignments = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $assignments,
        ]);
    }

    /**
     * DB 배분 단건 등록
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|string',
            'agent_id' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $customer = Customer::where('customer_id', $validated['customer_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $agent = Agent::where('agent_id', $validated['agent_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $adminId = $request->user()->admin->admin_id;

        $assignment = DB::transaction(function () use ($validated, $customer, $agent, $adminId) {
            $assignment = CustomerAssignment::create([
                'customer_id' => $customer->customer_id,
                'agent_id' => $agent->agent_id,
                'admin_id' => $adminId,
                'assignment_type' => 'NEW',
                'assignment_date' => now()->toDateString(),
                'notes' => $validated['notes'] ?? null,
                'created_by_id' => $adminId,
            ]);

            $customer->update(['agent_id' => $agent->agent_id]);

            Notification::create([
                'receiver_id' => $agent->agent_id,
                'receiver_type' => 'AGENT',
                'sender_id' => $adminId,
                'sender_type' => 'ADMIN',
                'notification_type' => 'ASSIGNMENT',
                'title' => 'DB 배분 알림',
                'content' => "새로운 고객 '{$customer->name}'님이 배분되었습니다.",
                'is_read' => false,
                'sent_at' => now(),
            ]);

            return $assignment;
        });

        $assignment->load([
            'customer:customer_id,name,phone',
            'agent:agent_id,name,phone',
        ]);

        return response()->json([
            'success' => true,
            'data' => $assignment,
            'message' => 'DB 배분이 등록되었습니다.',
        ], 201);
    }

    /**
     * DB 배분 대량 등록
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'assignments' => 'required|array|min:1',
            'assignments.*.customer_id' => 'required|string',
            'assignments.*.agent_id' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $adminId = $request->user()->admin->admin_id;
        $notes = $validated['notes'] ?? null;

        $createdCount = DB::transaction(function () use ($validated, $adminId, $notes) {
            $count = 0;

            foreach ($validated['assignments'] as $item) {
                $customer = Customer::where('customer_id', $item['customer_id'])
                    ->where('is_active', true)
                    ->first();

                $agent = Agent::where('agent_id', $item['agent_id'])
                    ->where('is_active', true)
                    ->first();

                if (!$customer || !$agent) {
                    continue;
                }

                CustomerAssignment::create([
                    'customer_id' => $customer->customer_id,
                    'agent_id' => $agent->agent_id,
                    'admin_id' => $adminId,
                    'assignment_type' => 'NEW',
                    'assignment_date' => now()->toDateString(),
                    'notes' => $notes,
                    'created_by_id' => $adminId,
                ]);

                $customer->update(['agent_id' => $agent->agent_id]);

                Notification::create([
                    'receiver_id' => $agent->agent_id,
                    'receiver_type' => 'AGENT',
                    'sender_id' => $adminId,
                    'sender_type' => 'ADMIN',
                    'notification_type' => 'ASSIGNMENT',
                    'title' => 'DB 배분 알림',
                    'content' => "새로운 고객 '{$customer->name}'님이 배분되었습니다.",
                    'is_read' => false,
                    'sent_at' => now(),
                ]);

                $count++;
            }

            return $count;
        });

        return response()->json([
            'success' => true,
            'data' => ['created_count' => $createdCount],
            'message' => "{$createdCount}건의 DB 배분이 등록되었습니다.",
        ], 201);
    }

    /**
     * 청구 배정 이력 조회
     */
    public function claimAssignments(Request $request): JsonResponse
    {
        $query = ClaimRequest::whereNotNull('assigned_agent_id')
            ->with(['assignedAgent:agent_id,name,phone', 'hospital:hospital_id,hospital_name']);

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $query->orderBy('updated_at', 'desc');

        $perPage = min(max((int) $request->get('per_page', 15), 1), 100);
        $assignments = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $assignments,
        ]);
    }

    /**
     * DB 배분 삭제 (배분 취소)
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $assignment = CustomerAssignment::findOrFail($id);

        $customer = Customer::where('customer_id', $assignment->customer_id)->first();

        if ($customer) {
            $customer->update(['agent_id' => null]);
        }

        $assignment->delete();

        return response()->json([
            'success' => true,
            'message' => 'DB 배분이 삭제되었습니다.',
        ]);
    }
}
