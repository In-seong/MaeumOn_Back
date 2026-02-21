<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\InsuranceClaim;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentClaimController extends Controller
{
    /**
     * 설계사 담당 고객의 보험청구 목록 (읽기 전용)
     */
    public function index(Request $request): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $query = InsuranceClaim::whereHas('customer', function ($q) use ($agentId) {
            $q->where('agent_id', $agentId);
        })->with([
            'customer:customer_id,name,phone',
            'claimForm:claim_form_id,form_name,company_id',
            'claimForm.insuranceCompany:company_id,company_name',
        ]);

        // 상태 필터
        if ($request->has('claim_status')) {
            $query->where('claim_status', $request->claim_status);
        }

        // 날짜 필터
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $claims = $query->orderBy('created_at', 'desc')
            ->paginate(min(max((int) $request->get('per_page', 15), 1), 100));

        return response()->json([
            'success' => true,
            'data' => $claims,
        ]);
    }

    /**
     * 보험청구 상세
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $claim = InsuranceClaim::whereHas('customer', function ($q) use ($agentId) {
            $q->where('agent_id', $agentId);
        })->with([
            'customer:customer_id,name,phone',
            'claimForm:claim_form_id,form_name,company_id',
            'claimForm.insuranceCompany:company_id,company_name',
            'fieldValues.formField:form_field_id,field_name,field_label,field_type',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $claim,
        ]);
    }
}
