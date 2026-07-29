<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PiiLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCustomerController extends Controller
{
    /**
     * 고객 목록 조회 (관리자 - 전체 고객)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Customer::with('agent:agent_id,name');

        // 검색 (이름, 전화번호, 주민번호)
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('resident_number', 'like', "%{$search}%");
            });
        }

        // 담당 설계사 필터 (agent_id=null 이면 미배정 고객만)
        if ($request->has('agent_id')) {
            if ($request->agent_id === 'null') {
                $query->whereNull('agent_id');
            } else {
                $query->where('agent_id', $request->agent_id);
            }
        }

        // 등록일 필터 (이후)
        if ($request->has('created_after')) {
            $query->where('created_at', '>', $request->created_after);
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

        $customers->getCollection()->transform(function ($customer) {
            $customer->makeHidden('resident_number');
            return $customer;
        });

        return response()->json([
            'success' => true,
            'data' => $customers,
        ]);
    }

    /**
     * 고객 등록 (관리자)
     */
    public function store(Request $request): JsonResponse
    {
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
            'telecom' => 'nullable|string|max:20',
            'acquisition_channel' => 'nullable|string|max:50',
            'agent_id' => 'nullable|string|max:8',
        ]);

        // Customer ID 생성 (C + 7자리 순번)
        $lastCustomer = Customer::where('customer_id', 'like', 'C%')
            ->orderByRaw('CAST(SUBSTRING(customer_id, 2) AS UNSIGNED) DESC')
            ->first();
        $nextNum = $lastCustomer
            ? (int) substr($lastCustomer->customer_id, 1) + 1
            : 1;
        $customerId = 'C' . str_pad((string) $nextNum, 7, '0', STR_PAD_LEFT);

        // 주민번호 하이픈 제거 (DB: char(13), 숫자만 저장)
        if (!empty($validated['resident_number'])) {
            $validated['resident_number'] = preg_replace('/\D/', '', $validated['resident_number']);
        }

        // 전화번호 하이픈 제거
        if (!empty($validated['phone'])) {
            $validated['phone'] = preg_replace('/\D/', '', $validated['phone']);
        }

        $customer = Customer::create(array_merge($validated, [
            'customer_id' => $customerId,
            'is_active' => true,
        ]));

        return response()->json([
            'success' => true,
            'data' => $customer,
            'message' => '고객이 등록되었습니다.',
        ], 201);
    }

    /**
     * 고객 상세 조회 (관리자)
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $customer = Customer::with([
                'contracts.insuranceCompany:company_id,company_name',
                'insuranceClaims' => function ($query) {
                    $query->orderBy('created_at', 'desc')->limit(10);
                },
                'memos' => function ($query) {
                    $query->orderBy('memo_date', 'desc')->limit(10);
                },
                'medicalRecords' => function ($query) {
                    $query->orderBy('created_at', 'desc')->limit(20);
                },
                'agent:agent_id,name,phone',
            ])
            ->where('customer_id', $id)
            ->firstOrFail();

        $data = $customer->toArray();
        $data['resident_number_masked'] = $customer->getMaskedResidentNumber();
        unset($data['resident_number']);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * 고객 정보 수정 (관리자)
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $customer = Customer::where('customer_id', $id)->firstOrFail();

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
            'telecom' => 'nullable|string|max:20',
            'acquisition_channel' => 'nullable|string|max:50',
            'agent_id' => 'nullable|string|max:8',
        ]);

        // 주민번호/전화번호 하이픈 제거
        if (!empty($validated['resident_number'])) {
            $validated['resident_number'] = preg_replace('/\D/', '', $validated['resident_number']);
        }
        if (!empty($validated['phone'])) {
            $validated['phone'] = preg_replace('/\D/', '', $validated['phone']);
        }

        $customer->update($validated);

        return response()->json([
            'success' => true,
            'data' => $customer->fresh(),
            'message' => '고객 정보가 수정되었습니다.',
        ]);
    }

    /**
     * 주민번호 마스킹 해제 (평문 반환 + PII 로그)
     */
    public function unmaskResidentNumber(Request $request, string $id): JsonResponse
    {
        $customer = Customer::where('customer_id', $id)->firstOrFail();

        if (!$customer->resident_number) {
            return response()->json([
                'success' => false,
                'message' => '주민번호가 등록되지 않은 고객입니다.',
            ], 404);
        }

        $adminId = $request->user()?->id ?? $request->header('X-Admin-Id', 'unknown');

        PiiLog::log(
            (string) $adminId,
            'UNMASK',
            'customer',
            $id,
            'resident_number',
            $request->ip()
        );

        return response()->json([
            'success' => true,
            'data' => [
                'resident_number' => $customer->resident_number,
            ],
        ]);
    }

    /**
     * 고객 비활성화 (소프트 삭제)
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $customer = Customer::where('customer_id', $id)->firstOrFail();

        $customer->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'data' => $customer->fresh(),
            'message' => '고객이 비활성화되었습니다.',
        ]);
    }
}
