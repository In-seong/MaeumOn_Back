<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminAgentController extends Controller
{
    /**
     * 설계사 목록 조회 (관리자)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Agent::query()
            ->withCount(['customers', 'contracts']);

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
        ]);

        // 전화번호 하이픈 제거
        if (!empty($validated['phone'])) {
            $validated['phone'] = preg_replace('/\D/', '', $validated['phone']);
        }

        $agent = DB::transaction(function () use ($validated) {
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

            return $agent->load('account:account_id,username,role,is_active');
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
        ]);

        // 전화번호 하이픈 제거
        if (!empty($validated['phone'])) {
            $validated['phone'] = preg_replace('/\D/', '', $validated['phone']);
        }

        DB::transaction(function () use ($agent, $validated) {
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

            // Agent 정보 수정 (username, password 제외)
            $agentData = array_diff_key($validated, array_flip(['username', 'password']));
            if (!empty($agentData)) {
                $agent->update($agentData);
            }
        });

        return response()->json([
            'success' => true,
            'data' => $agent->fresh()->load('account:account_id,username,role,is_active'),
            'message' => '설계사 정보가 수정되었습니다.',
        ]);
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
}
