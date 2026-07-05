<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CodefApiLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminCodefBillingController extends Controller
{
    /**
     * 월별 설계사별 API 사용량 요약
     * GET /api/admin/codef-billing/summary
     * ?month=2026-07 (기본: 이번 달)
     */
    public function monthlySummary(Request $request): JsonResponse
    {
        $month = $request->input('month', now()->format('Y-m'));

        $query = CodefApiLog::join('agent', 'codef_api_logs.agent_id', '=', 'agent.agent_id')
            ->select(
                'codef_api_logs.agent_id',
                'agent.name as agent_name',
                DB::raw("SUM(CASE WHEN api_type = 'insurance' THEN 1 ELSE 0 END) as insurance_count"),
                DB::raw("SUM(CASE WHEN api_type = 'medical' THEN 1 ELSE 0 END) as medical_count"),
                DB::raw("SUM(CASE WHEN api_type = 'checkup' THEN 1 ELSE 0 END) as checkup_count"),
                DB::raw("SUM(CASE WHEN api_type = 'health_age' THEN 1 ELSE 0 END) as health_age_count"),
                DB::raw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count"),
                DB::raw('COUNT(*) as total_count'),
            )
            ->groupBy('codef_api_logs.agent_id', 'agent.name')
            ->orderByDesc('total_count');

        if ($month) {
            $query->where('billing_month', $month);
        }

        $summary = $query->get();

        $totalAll = $summary->sum('total_count');

        return response()->json([
            'success' => true,
            'data' => [
                'month' => $month,
                'agents' => $summary,
                'total' => $totalAll,
            ],
        ]);
    }

    /**
     * 설계사별 상세 사용 로그
     * GET /api/admin/codef-billing/logs
     * ?month=2026-07&agent_id=AG000001&api_type=insurance
     */
    public function logs(Request $request): JsonResponse
    {
        $query = CodefApiLog::with(['customer:customer_id,name,phone'])
            ->join('agent', 'codef_api_logs.agent_id', '=', 'agent.agent_id')
            ->select('codef_api_logs.*', 'agent.name as agent_name');

        if ($request->has('month')) {
            $query->where('billing_month', $request->month);
        }
        if ($request->has('agent_id')) {
            $query->where('codef_api_logs.agent_id', $request->agent_id);
        }
        if ($request->has('api_type')) {
            $query->where('api_type', $request->api_type);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $logs = $query->orderByDesc('codef_api_logs.created_at')
            ->paginate($request->input('per_page', 30));

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * 정산 완료 처리
     * POST /api/admin/codef-billing/mark-billed
     * {month: "2026-07", agent_id: "AG000001"} — agent_id 없으면 해당 월 전체
     */
    public function markBilled(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'agent_id' => 'nullable|string',
        ]);

        $query = CodefApiLog::where('billing_month', $validated['month'])
            ->where('status', CodefApiLog::STATUS_SUCCESS)
            ->where('billed', false);

        if (!empty($validated['agent_id'])) {
            $query->where('agent_id', $validated['agent_id']);
        }

        $updated = $query->update([
            'billed' => true,
            'billed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "{$updated}건 정산 완료 처리되었습니다.",
            'data' => ['updated_count' => $updated],
        ]);
    }
}
