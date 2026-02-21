<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgentCustomerController extends Controller
{
    /**
     * 고객 목록 조회
     */
    public function index(Request $request): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $query = Customer::where('agent_id', $agentId);

        // 검색 (이름, 전화번호)
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // 활성 상태 필터
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        // 정렬
        $sortField = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        if (in_array($sortField, ['name', 'created_at'])) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        }

        $perPage = min(max((int) $request->get('per_page', 15), 1), 100);
        $customers = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $customers,
        ]);
    }

    /**
     * 고객 등록
     */
    public function store(Request $request): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:100',
            'resident_number' => 'nullable|string|max:20',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:M,F,OTHER',
            'address' => 'nullable|string|max:200',
            'detailed_address' => 'nullable|string|max:200',
            'job' => 'nullable|string|max:50',
        ]);

        // Customer ID 생성 (C + 7자리 순번)
        $lastCustomer = Customer::where('customer_id', 'like', 'C%')
            ->orderByRaw('CAST(SUBSTRING(customer_id, 2) AS UNSIGNED) DESC')
            ->first();
        $nextNum = $lastCustomer
            ? (int) substr($lastCustomer->customer_id, 1) + 1
            : 1;
        $customerId = 'C' . str_pad((string) $nextNum, 7, '0', STR_PAD_LEFT);

        $customer = Customer::create(array_merge($validated, [
            'customer_id' => $customerId,
            'agent_id' => $agentId,
            'is_active' => true,
        ]));

        return response()->json([
            'success' => true,
            'data' => $customer,
            'message' => '고객이 등록되었습니다.',
        ], 201);
    }

    /**
     * 고객 상세 조회
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $customer = Customer::where('agent_id', $agentId)
            ->with([
                'contracts.insuranceCompany:company_id,company_name',
                'insuranceClaims' => function ($query) {
                    $query->orderBy('created_at', 'desc')->limit(10);
                },
                'memos' => function ($query) {
                    $query->orderBy('memo_date', 'desc')->limit(10);
                },
            ])
            ->where('customer_id', $id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $customer,
        ]);
    }

    /**
     * 고객 정보 수정
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $customer = Customer::where('agent_id', $agentId)
            ->where('customer_id', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:50',
            'phone' => 'sometimes|string|max:20',
            'email' => 'nullable|email|max:100',
            'resident_number' => 'nullable|string|max:20',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:M,F,OTHER',
            'address' => 'nullable|string|max:200',
            'detailed_address' => 'nullable|string|max:200',
            'job' => 'nullable|string|max:50',
        ]);

        $customer->update($validated);

        return response()->json([
            'success' => true,
            'data' => $customer->fresh(),
            'message' => '고객 정보가 수정되었습니다.',
        ]);
    }

    /**
     * 고객 비활성화 (소프트 삭제)
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $customer = Customer::where('agent_id', $agentId)
            ->where('customer_id', $id)
            ->firstOrFail();

        $customer->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'data' => $customer->fresh(),
            'message' => '고객이 비활성화되었습니다.',
        ]);
    }

    /**
     * 고객 계약 목록 조회
     */
    public function contracts(Request $request, string $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $customer = Customer::where('agent_id', $agentId)
            ->where('customer_id', $id)
            ->firstOrFail();

        $perPage = min(max((int) $request->get('per_page', 15), 1), 100);
        $contracts = Contract::where('customer_id', $customer->customer_id)
            ->with('insuranceCompany:company_id,company_name')
            ->orderBy('contract_date', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $contracts,
        ]);
    }
}
