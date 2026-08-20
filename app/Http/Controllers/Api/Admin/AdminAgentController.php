<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\BranchFilterable;
use App\Models\Account;
use App\Models\Agent;
use App\Models\BatchClaim;
use App\Models\CalendarEvent;
use App\Models\ClaimRequest;
use App\Models\Consultation;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\CustomerAssignment;
use App\Models\CustomerStatus;
use App\Models\InsuranceClaim;
use App\Models\Reminder;
use App\Models\SatisfactionSurvey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminAgentController extends Controller
{
    use BranchFilterable;

    /**
     * 설계사 목록 조회 (관리자)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Agent::query()
            ->with('account:account_id,username')
            ->withCount(['customers', 'contracts']);

        $branchId = $this->resolveBranchId($request);
        $this->applyAgentBranchFilter($query, $branchId);

        // 활성 상태 필터 (기본값: 활성화만)
        if ($request->has('is_active') && $request->is_active !== 'all') {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        } elseif (!$request->has('is_active')) {
            $query->where('is_active', true);
        }

        // 검색 (이름, 사번, 전화번호)
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('employee_number', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // 정렬
        $sortField = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        if (in_array($sortField, ['name', 'employee_number', 'created_at', 'hire_date'])) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        }

        $perPage = min(max((int) $request->get('per_page', 15), 1), 100);
        $agents = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $agents,
        ]);
    }

    /**
     * 설계사 상세 조회 (관리자)
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $agent = Agent::with([
                'account:account_id,username,role,is_active',
                'branches:branch_id,branch_name',
                'performances' => function ($query) {
                    $query->orderByDesc('year')
                          ->orderByDesc('month')
                          ->limit(12);
                },
            ])
            ->withCount(['customers', 'contracts'])
            ->where('agent_id', $id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $agent,
        ]);
    }

    /**
     * 설계사 등록 (관리자)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|string|max:50|unique:account,username',
            'password' => 'required|string|min:6|max:100',
            'name' => 'required|string|max:50',
            'employee_number' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'office_location' => 'nullable|string|max:100',
            'specialization' => 'nullable|string|max:100',
            'hire_date' => 'nullable|date',
            'branch_id' => 'nullable|integer|exists:branch,branch_id',
        ]);

        // 전화번호 하이픈 제거
        if (!empty($validated['phone'])) {
            $validated['phone'] = preg_replace('/\D/', '', $validated['phone']);
        }

        $branchId = $this->resolveBranchIdForAgent($request, $validated);

        $agent = DB::transaction(function () use ($validated, $branchId) {
            // Account 생성
            $account = Account::create([
                'username' => $validated['username'],
                'password_hash' => Hash::make($validated['password']),
                'role' => Account::ROLE_AGENT,
                'is_active' => true,
            ]);

            // Agent ID 생성 (A + 7자리 순번)
            $lastAgent = Agent::where('agent_id', 'like', 'A%')
                ->orderByRaw('CAST(SUBSTRING(agent_id, 2) AS UNSIGNED) DESC')
                ->first();
            $nextNum = $lastAgent
                ? (int) substr($lastAgent->agent_id, 1) + 1
                : 1;
            $agentId = 'A' . str_pad((string) $nextNum, 7, '0', STR_PAD_LEFT);

            // Agent 생성
            $agent = Agent::create([
                'agent_id' => $agentId,
                'account_id' => $account->account_id,
                'name' => $validated['name'],
                'employee_number' => $validated['employee_number'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'office_location' => $validated['office_location'] ?? null,
                'specialization' => $validated['specialization'] ?? null,
                'hire_date' => $validated['hire_date'] ?? null,
                'is_active' => true,
            ]);

            if ($branchId) {
                $agent->branches()->sync([$branchId]);
            }

            return $agent->load(['account:account_id,username,role,is_active', 'branches:branch_id,branch_name']);
        });

        return response()->json([
            'success' => true,
            'data' => $agent,
            'message' => '설계사가 등록되었습니다.',
        ], 201);
    }

    /**
     * 설계사 정보 수정 (관리자)
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $agent = Agent::where('agent_id', $id)->firstOrFail();

        $validated = $request->validate([
            'username' => 'sometimes|string|max:50|unique:account,username,' . $agent->account_id . ',account_id',
            'password' => 'sometimes|string|min:6|max:100',
            'name' => 'sometimes|string|max:50',
            'employee_number' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'office_location' => 'nullable|string|max:100',
            'specialization' => 'nullable|string|max:100',
            'hire_date' => 'nullable|date',
            'branch_id' => 'nullable|integer|exists:branch,branch_id',
        ]);

        // 전화번호 하이픈 제거
        if (!empty($validated['phone'])) {
            $validated['phone'] = preg_replace('/\D/', '', $validated['phone']);
        }

        $branchId = $this->resolveBranchIdForAgent($request, $validated);

        DB::transaction(function () use ($agent, $validated, $branchId) {
            // Account 정보 수정 (username, password)
            if ($agent->account_id && (isset($validated['username']) || isset($validated['password']))) {
                $accountUpdate = [];
                if (isset($validated['username'])) {
                    $accountUpdate['username'] = $validated['username'];
                }
                if (isset($validated['password'])) {
                    $accountUpdate['password_hash'] = Hash::make($validated['password']);
                }
                Account::where('account_id', $agent->account_id)->update($accountUpdate);
            }

            // Agent 정보 수정 (username, password, branch_id 제외)
            $agentData = array_diff_key($validated, array_flip(['username', 'password', 'branch_id']));
            if (!empty($agentData)) {
                $agent->update($agentData);
            }

            if ($branchId) {
                $agent->branches()->sync([$branchId]);
            }
        });

        return response()->json([
            'success' => true,
            'data' => $agent->fresh()->load(['account:account_id,username,role,is_active', 'branches:branch_id,branch_name']),
            'message' => '설계사 정보가 수정되었습니다.',
        ]);
    }

    /**
     * 설계사 등록/수정 시 지사 ID 결정
     * 지사 관리자: 자기 지사 강제 적용 (요청값 무시)
     * 슈퍼 관리자: 요청에서 받은 branch_id 사용
     */
    private function resolveBranchIdForAgent(Request $request, array $validated): ?int
    {
        $admin = $request->user()?->admin;

        if ($admin && $admin->admin_role === 'BRANCH' && $admin->branch_id) {
            return (int) $admin->branch_id;
        }

        return isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;
    }

    /**
     * 설계사 비활성화 (소프트 삭제)
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $agent = Agent::where('agent_id', $id)->firstOrFail();

        DB::transaction(function () use ($agent) {
            $agent->update(['is_active' => false]);

            // 연결된 Account도 비활성화
            if ($agent->account_id) {
                Account::where('account_id', $agent->account_id)
                    ->update(['is_active' => false]);
            }
        });

        return response()->json([
            'success' => true,
            'data' => $agent->fresh(),
            'message' => '설계사가 비활성화되었습니다.',
        ]);
    }

    /**
     * 고객 재배분: 특정 설계사의 고객(및 관련 데이터)을 다른 설계사에게 이관
     */
    public function reassignCustomers(Request $request, string $fromAgentId): JsonResponse
    {
        $validated = $request->validate([
            'to_agent_id' => 'required|string|exists:agent,agent_id',
            'customer_ids' => 'required|array|min:1',
            'customer_ids.*' => 'string|exists:customer,customer_id',
        ]);

        $toAgentId = $validated['to_agent_id'];
        $customerIds = $validated['customer_ids'];

        if ($fromAgentId === $toAgentId) {
            return response()->json([
                'success' => false,
                'message' => '같은 설계사에게 재배분할 수 없습니다.',
            ], 422);
        }

        Agent::where('agent_id', $fromAgentId)->firstOrFail();
        $toAgent = Agent::where('agent_id', $toAgentId)->firstOrFail();

        // 실제로 원래 설계사에 속한 고객만 필터
        $validCustomerIds = Customer::where('agent_id', $fromAgentId)
            ->whereIn('customer_id', $customerIds)
            ->pluck('customer_id')
            ->toArray();

        if (empty($validCustomerIds)) {
            return response()->json([
                'success' => false,
                'message' => '이관할 고객이 없습니다.',
            ], 422);
        }

        $adminId = $request->user()->admin->admin_id ?? null;

        $counts = DB::transaction(function () use ($fromAgentId, $toAgentId, $validCustomerIds, $adminId) {
            $counts = ['customers' => count($validCustomerIds)];

            // 1. 고객 담당자 변경
            Customer::whereIn('customer_id', $validCustomerIds)
                ->update(['agent_id' => $toAgentId]);

            // 2. 보험 청구
            $counts['claims'] = InsuranceClaim::where('agent_id', $fromAgentId)
                ->whereIn('customer_id', $validCustomerIds)
                ->update(['agent_id' => $toAgentId]);

            // 3. 배치 청구
            $counts['batch_claims'] = BatchClaim::where('agent_id', $fromAgentId)
                ->whereIn('customer_id', $validCustomerIds)
                ->update(['agent_id' => $toAgentId]);

            // 4. 계약
            $counts['contracts'] = Contract::where('agent_id', $fromAgentId)
                ->whereIn('customer_id', $validCustomerIds)
                ->update(['agent_id' => $toAgentId]);

            // 5. 상담
            $counts['consultations'] = Consultation::where('assignee_id', $fromAgentId)
                ->whereIn('customer_id', $validCustomerIds)
                ->update(['assignee_id' => $toAgentId]);

            // 6. 고객 상태
            CustomerStatus::where('agent_id', $fromAgentId)
                ->whereIn('customer_id', $validCustomerIds)
                ->update(['agent_id' => $toAgentId]);

            // 7. 만족도 조사
            SatisfactionSurvey::where('agent_id', $fromAgentId)
                ->whereIn('customer_id', $validCustomerIds)
                ->update(['agent_id' => $toAgentId]);

            // 8. 캘린더 일정
            $counts['calendar_events'] = CalendarEvent::where('agent_id', $fromAgentId)
                ->whereIn('customer_id', $validCustomerIds)
                ->update(['agent_id' => $toAgentId]);

            // 9. 캘린더 알림 (이관된 일정의 알림)
            $eventIds = CalendarEvent::where('agent_id', $toAgentId)
                ->whereIn('customer_id', $validCustomerIds)
                ->pluck('event_id')
                ->toArray();
            if (!empty($eventIds)) {
                $counts['reminders'] = Reminder::where('agent_id', $fromAgentId)
                    ->whereIn('event_id', $eventIds)
                    ->update(['agent_id' => $toAgentId]);
            }

            // 10. 청구 요청 (insurance_claim에 연결된 건)
            $claimIds = InsuranceClaim::where('agent_id', $toAgentId)
                ->whereIn('customer_id', $validCustomerIds)
                ->pluck('claim_id')
                ->toArray();
            if (!empty($claimIds)) {
                $counts['claim_requests'] = ClaimRequest::where('assigned_agent_id', $fromAgentId)
                    ->whereIn('linked_claim_id', $claimIds)
                    ->update(['assigned_agent_id' => $toAgentId]);
            }

            // 11. 재배분 이력 기록
            $now = now();
            foreach ($validCustomerIds as $customerId) {
                CustomerAssignment::create([
                    'customer_id' => $customerId,
                    'agent_id' => $toAgentId,
                    'admin_id' => $adminId,
                    'assignment_type' => 'reassign',
                    'assignment_date' => $now->toDateString(),
                    'notes' => "재배분: {$fromAgentId} → {$toAgentId}",
                    'created_by_id' => $adminId,
                ]);
            }

            return $counts;
        });

        return response()->json([
            'success' => true,
            'data' => $counts,
            'message' => "{$counts['customers']}명의 고객이 {$toAgent->name} 설계사에게 재배분되었습니다.",
        ]);
    }
}
