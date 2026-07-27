<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CorporateInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCorporateInquiryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CorporateInquiry::with('agent:agent_id,name,phone')
            ->orderByDesc('created_at');

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

        $count = 0;
        foreach ($validated['inquiry_ids'] as $inquiryId) {
            $inquiry = CorporateInquiry::find($inquiryId);
            if ($inquiry) {
                $updateData = [
                    'agent_id' => $validated['agent_id'],
                    'assigned_at' => now(),
                    'status' => 'IN_PROGRESS',
                ];
                if (!empty($validated['notes'])) {
                    $updateData['notes'] = $validated['notes'];
                }
                $inquiry->update($updateData);
                $count++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$count}건이 배분되었습니다.",
        ]);
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
}
