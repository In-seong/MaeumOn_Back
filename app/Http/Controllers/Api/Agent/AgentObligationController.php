<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\DisclosureObligation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentObligationController extends Controller
{
    /**
     * 담당 고객의 고지의무 목록 조회
     */
    public function index(Request $request): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $query = DisclosureObligation::whereHas('customer', function ($q) use ($agentId) {
            $q->where('agent_id', $agentId);
        });

        if ($request->filled('is_disclosed')) {
            $query->where('is_disclosed', filter_var($request->get('is_disclosed'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('expiring_soon') && $request->get('expiring_soon')) {
            $query->where('is_disclosed', false)
                ->where('tracking_end_date', '>=', now())
                ->where('tracking_end_date', '<=', now()->addDays(30));
        }

        if ($request->filled('urgency')) {
            $urgency = (int) $request->get('urgency');

            if ($urgency === 0) {
                $query->whereDate('tracking_end_date', now()->toDateString());
            } else {
                $query->where('tracking_end_date', '>=', now())
                    ->where('tracking_end_date', '<=', now()->addDays($urgency));
            }
        }

        $obligations = $query->with(['customer', 'medicalRecord'])
            ->orderBy('tracking_end_date', 'asc')
            ->paginate(min(max((int) $request->get('per_page', 15), 1), 100));

        return response()->json([
            'success' => true,
            'data' => $obligations,
            'message' => '고지의무 목록을 조회했습니다.',
        ]);
    }

    /**
     * 고지의무 상세 조회
     */
    public function show(Request $request, $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $obligation = DisclosureObligation::where('disclosure_id', $id)
            ->whereHas('customer', function ($q) use ($agentId) {
                $q->where('agent_id', $agentId);
            })
            ->with(['customer', 'medicalRecord'])
            ->first();

        if (!$obligation) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => '고지의무 정보를 찾을 수 없습니다.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $obligation,
            'message' => '고지의무 상세 정보를 조회했습니다.',
        ]);
    }
}
