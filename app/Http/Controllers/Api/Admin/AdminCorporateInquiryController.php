<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\BranchFilterable;
use App\Models\CorporateInquiry;
use App\Models\Notification;
use App\Services\FcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AdminCorporateInquiryController extends Controller
{
    use BranchFilterable;

    public function index(Request $request): JsonResponse
    {
        $query = CorporateInquiry::with('agent:agent_id,name,phone')
            ->orderByDesc('created_at');

        $branchId = $this->resolveBranchId($request);
        $this->applyAgentBranchFilter($query, $branchId, 'agent.branches');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                  ->orWhere('ceo_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->input('date_from'))->startOfDay(),
                Carbon::parse($request->input('date_to'))->endOfDay(),
            ]);
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_direction', 'desc');
        $allowedSorts = ['id', 'company_name', 'ceo_name', 'phone', 'annual_revenue', 'industry', 'consultation_field', 'status', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->reorder($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $perPage = $request->input('per_page', 20);
        $inquiries = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $inquiries,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $inquiry = CorporateInquiry::with('agent:agent_id,name,phone,email')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $inquiry,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $inquiry = CorporateInquiry::findOrFail($id);

        $validated = $request->validate([
            'status' => 'sometimes|string|in:NEW,IN_PROGRESS,COMPLETED',
            'notes' => 'sometimes|nullable|string|max:2000',
        ]);

        $inquiry->update($validated);

        return response()->json([
            'success' => true,
            'message' => '업데이트되었습니다.',
            'data' => $inquiry->fresh()->load('agent:agent_id,name,phone,email'),
        ]);
    }

    public function assign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'inquiry_ids' => 'required|array|min:1',
            'inquiry_ids.*' => 'required|integer|exists:corporate_inquiries,id',
            'agent_id' => 'required|string|exists:agent,agent_id',
            'notes' => 'nullable|string|max:500',
        ]);

        $adminId = $request->user()?->admin_id ?? ($request->user()?->id ?? null);
        $agentId = $validated['agent_id'];

        $count = 0;
        foreach ($validated['inquiry_ids'] as $inquiryId) {
            $inquiry = CorporateInquiry::find($inquiryId);
            if ($inquiry) {
                $updateData = [
                    'agent_id' => $agentId,
                    'assigned_at' => now(),
                    'status' => 'IN_PROGRESS',
                ];
                if (!empty($validated['notes'])) {
                    $updateData['notes'] = $validated['notes'];
                }
                $inquiry->update($updateData);
                $count++;

                Notification::create([
                    'receiver_id' => $agentId,
                    'receiver_type' => 'AGENT',
                    'sender_id' => $adminId,
                    'sender_type' => 'ADMIN',
                    'notification_type' => 'ASSIGNMENT',
                    'title' => '기업 DB 배분 알림',
                    'content' => "새로운 기업 문의 '{$inquiry->company_name}'이(가) 배분되었습니다.",
                    'is_read' => false,
                    'sent_at' => now(),
                ]);
            }
        }

        if ($count > 0) {
            try {
                $companyNames = CorporateInquiry::whereIn('id', $validated['inquiry_ids'])->pluck('company_name')->implode(', ');
                app(FcmService::class)->sendToUsers('AGENT', [$agentId], '기업 DB 배분 알림', "새로운 기업 문의 {$count}건이 배분되었습니다. ({$companyNames})");
            } catch (\Exception $e) {
                Log::error('기업 배분 알림 FCM 발송 실패', ['agent_id' => $agentId, 'error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$count}건이 배분되었습니다.",
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:100',
            'address' => 'nullable|string|max:200',
            'ceo_name' => 'required|string|max:50',
            'phone' => 'required|string|max:20',
            'industry' => 'nullable|string|max:50',
            'annual_revenue' => 'nullable|string|max:50',
            'consultation_field' => 'nullable|string|max:100',
            'agent_id' => 'nullable|string|exists:agent,agent_id',
        ]);

        if (!empty($validated['phone'])) {
            $validated['phone'] = preg_replace('/\D/', '', $validated['phone']);
        }

        $data = array_merge($validated, [
            'privacy_agreed' => true,
            'status' => 'NEW',
        ]);

        if (!empty($validated['agent_id'])) {
            $data['assigned_at'] = now();
            $data['status'] = 'IN_PROGRESS';
        }

        $inquiry = CorporateInquiry::create($data);

        if (!empty($validated['agent_id'])) {
            $adminId = $request->user()?->admin_id ?? ($request->user()?->id ?? null);

            Notification::create([
                'receiver_id' => $validated['agent_id'],
                'receiver_type' => 'AGENT',
                'sender_id' => $adminId,
                'sender_type' => 'ADMIN',
                'notification_type' => 'ASSIGNMENT',
                'title' => '기업 DB 배분 알림',
                'content' => "새로운 기업 문의 '{$inquiry->company_name}'이(가) 배분되었습니다.",
                'is_read' => false,
                'sent_at' => now(),
            ]);

            try {
                app(FcmService::class)->sendToUsers('AGENT', [$validated['agent_id']], '기업 DB 배분 알림', "새로운 기업 문의 '{$inquiry->company_name}'이(가) 배분되었습니다.");
            } catch (\Exception $e) {
                Log::error('기업 배분 알림 FCM 발송 실패', ['agent_id' => $validated['agent_id'], 'error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $inquiry->load('agent:agent_id,name,phone'),
            'message' => '기업 문의가 등록되었습니다.',
        ], 201);
    }

    public function unassigned(Request $request): JsonResponse
    {
        $query = CorporateInquiry::whereNull('agent_id')
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                  ->orWhere('ceo_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $inquiries = $query->get();

        return response()->json([
            'success' => true,
            'data' => $inquiries,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $inquiry = CorporateInquiry::findOrFail($id);
        $inquiry->delete();

        return response()->json([
            'success' => true,
            'message' => '기업 문의가 삭제되었습니다.',
        ]);
    }
}
