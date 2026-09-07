<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\CodefApiLog;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\PiiLog;
use App\Services\FcmService;
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

        // 활성 상태 필터 (기본: 활성 고객만)
        $isActive = $request->has('is_active')
            ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)
            : true;
        $query->where('is_active', $isActive);

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
            'telecom' => 'nullable|string|max:20',
            'acquisition_channel' => 'nullable|string|max:50',
            'acquisition_note' => 'nullable|string|max:500',
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

        // 전화번호 하이픈 제거 (DB: varchar(20)이지만 일관성)
        if (!empty($validated['phone'])) {
            $validated['phone'] = preg_replace('/\D/', '', $validated['phone']);
        }

        // 중복 고객 체크: 이름+전화번호 or 이름+주민등록번호
        $duplicate = Customer::where('agent_id', $agentId)
            ->where('is_active', true)
            ->where('name', $validated['name'])
            ->where(function ($q) use ($validated) {
                $q->where('phone', $validated['phone']);
                if (!empty($validated['resident_number'])) {
                    $q->orWhere('resident_number', $validated['resident_number']);
                }
            })
            ->first();

        if ($duplicate) {
            $matchType = $duplicate->phone === $validated['phone']
                ? '전화번호' : '주민등록번호';
            return response()->json([
                'success' => false,
                'message' => "동일한 이름과 {$matchType}를 가진 고객이 이미 등록되어 있습니다.",
            ], 422);
        }

        $customer = Customer::create(array_merge($validated, [
            'customer_id' => $customerId,
            'agent_id' => $agentId,
            'is_active' => true,
        ]));

        $agent = $request->user()->agent;
        $this->notifyAdminsCustomerRegistered($agent->name ?? $agentId, $customer->name);

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
                    $query->with('claimForm.insuranceCompany:company_id,company_name')
                        ->orderBy('created_at', 'desc')->limit(10);
                },
                'memos' => function ($query) {
                    $query->orderBy('memo_date', 'desc')->limit(10);
                },
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
     * 주민번호 마스킹 해제 (평문 반환 + PII 로그)
     */
    public function unmaskResidentNumber(Request $request, string $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $customer = Customer::where('agent_id', $agentId)
            ->where('customer_id', $id)
            ->firstOrFail();

        if (!$customer->resident_number) {
            return response()->json([
                'success' => false,
                'message' => '주민번호가 등록되지 않은 고객입니다.',
            ], 404);
        }

        PiiLog::log(
            $agentId,
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
            'telecom' => 'nullable|string|max:20',
            'acquisition_channel' => 'nullable|string|max:50',
            'acquisition_note' => 'nullable|string|max:500',
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

    /**
     * 계약 등록
     */
    public function storeContract(Request $request, string $customerId): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        Customer::where('agent_id', $agentId)
            ->where('customer_id', $customerId)
            ->firstOrFail();

        $validated = $request->validate([
            'company_id' => 'required|integer|exists:insurance_company,company_id',
            'insurance_product' => 'required|string|max:200',
            'contract_number' => 'nullable|string|max:50',
            'contract_amount' => 'nullable|numeric|min:0',
            'contract_date' => 'nullable|date',
            'expiration_date' => 'nullable|date',
            'contract_status' => 'nullable|string|in:active,expired,cancelled',
            'payment_method' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',
        ]);

        $contract = Contract::create([
            ...$validated,
            'customer_id' => $customerId,
            'agent_id' => $agentId,
            'contract_status' => $validated['contract_status'] ?? 'active',
            'created_by_id' => $request->user()->account_id,
        ]);

        $contract->load('insuranceCompany:company_id,company_name');

        return response()->json([
            'success' => true,
            'data' => $contract,
            'message' => '계약이 등록되었습니다.',
        ], 201);
    }

    /**
     * 계약 수정
     */
    public function updateContract(Request $request, string $customerId, int $contractId): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        Customer::where('agent_id', $agentId)
            ->where('customer_id', $customerId)
            ->firstOrFail();

        $contract = Contract::where('customer_id', $customerId)
            ->where('contract_id', $contractId)
            ->firstOrFail();

        $validated = $request->validate([
            'company_id' => 'sometimes|integer|exists:insurance_company,company_id',
            'insurance_product' => 'sometimes|string|max:200',
            'contract_number' => 'nullable|string|max:50',
            'contract_amount' => 'nullable|numeric|min:0',
            'contract_date' => 'nullable|date',
            'expiration_date' => 'nullable|date',
            'contract_status' => 'nullable|string|in:active,expired,cancelled',
            'payment_method' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',
        ]);

        $contract->update([
            ...$validated,
            'updated_by_id' => $request->user()->account_id,
        ]);

        $contract->load('insuranceCompany:company_id,company_name');

        return response()->json([
            'success' => true,
            'data' => $contract,
            'message' => '계약이 수정되었습니다.',
        ]);
    }

    /**
     * 고객의 CODEF 조회 이력
     */
    public function codefLogs(Request $request, string $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        Customer::where('agent_id', $agentId)
            ->where('customer_id', $id)
            ->firstOrFail();

        $logs = CodefApiLog::where('customer_id', $id)
            ->orderBy('created_at', 'desc')
            ->get(['log_id', 'api_type', 'api_action', 'status', 'result_count', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * 계약 삭제
     */
    public function destroyContract(Request $request, string $customerId, int $contractId): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        Customer::where('agent_id', $agentId)
            ->where('customer_id', $customerId)
            ->firstOrFail();

        $contract = Contract::where('customer_id', $customerId)
            ->where('contract_id', $contractId)
            ->firstOrFail();

        $contract->update(['contract_status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => '계약이 해지 처리되었습니다.',
        ]);
    }

    private function notifyAdminsCustomerRegistered(string $agentName, string $customerName): void
    {
        $adminIds = Admin::where('is_active', 1)->pluck('admin_id')->all();
        if (empty($adminIds)) {
            return;
        }

        $title = '신규 고객 등록';
        $body = "설계사 {$agentName}님이 신규 고객 '{$customerName}'을 등록했습니다.";
        $now = now();

        $rows = array_map(fn($id) => [
            'receiver_id' => $id,
            'receiver_type' => 'ADMIN',
            'sender_type' => 'SYSTEM',
            'notification_type' => 'customer_registered',
            'title' => $title,
            'content' => $body,
            'is_read' => false,
            'sent_at' => $now,
            'created_at' => $now,
        ], $adminIds);
        Notification::insert($rows);

        try {
            app(FcmService::class)->sendToUsers('ADMIN', $adminIds, $title, $body);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('고객 등록 알림 FCM 발송 실패', ['error' => $e->getMessage()]);
        }
    }
}
