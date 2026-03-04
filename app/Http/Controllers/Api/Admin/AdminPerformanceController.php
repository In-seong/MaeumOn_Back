<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Performance;
use App\Models\CustomerAssignment;
use App\Models\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminPerformanceController extends Controller
{
    /**
     * 실적 요약 (SFR-043)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function summary(Request $request): JsonResponse
    {
        $period = $request->get('period', 'month');

        $startDate = match ($period) {
            'day' => Carbon::today(),
            'week' => Carbon::now()->startOfWeek(),
            default => Carbon::now()->startOfMonth(),
        };

        $totalAssignments = CustomerAssignment::where('created_at', '>=', $startDate)->count();
        $totalContracts = Contract::where('created_at', '>=', $startDate)->count();
        $totalContractAmount = Contract::where('created_at', '>=', $startDate)->sum('contract_amount');
        $dbProcessingRate = $totalAssignments > 0
            ? round(($totalContracts / $totalAssignments) * 100, 1)
            : 0;
        $conversionRate = $dbProcessingRate;

        return response()->json([
            'success' => true,
            'data' => [
                'total_assignments' => $totalAssignments,
                'total_contracts' => $totalContracts,
                'total_contract_amount' => (float) $totalContractAmount,
                'db_processing_rate' => $dbProcessingRate,
                'conversion_rate' => $conversionRate,
            ],
        ]);
    }

    /**
     * 설계사별 실적 목록
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function agents(Request $request): JsonResponse
    {
        $now = Carbon::now();
        $perPage = min(max((int) $request->get('per_page', 15), 1), 100);

        $agents = Agent::where('is_active', true)
            ->with(['performances' => function ($q) use ($now) {
                $q->where('year', $now->year)->where('month', $now->month);
            }])
            ->withCount(['customers', 'contracts'])
            ->paginate($perPage);

        $agents->getCollection()->transform(function ($agent) {
            $currentPerf = $agent->performances->first();

            return [
                'agent_id' => $agent->agent_id,
                'agent_name' => $agent->name,
                'db_assigned_count' => $currentPerf?->db_assigned_count ?? 0,
                'contract_count' => $currentPerf?->contract_count ?? 0,
                'contract_amount' => (float) ($currentPerf?->contract_amount ?? 0),
                'consultation_count' => $currentPerf?->consultation_count ?? 0,
                'processing_rate' => ($currentPerf?->db_assigned_count ?? 0) > 0
                    ? round((($currentPerf?->contract_count ?? 0) / $currentPerf->db_assigned_count) * 100, 1)
                    : 0,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $agents,
        ]);
    }

    /**
     * 설계사 월별 실적 추이 (최근 12개월)
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function agentDetail(Request $request, string $id): JsonResponse
    {
        $agent = Agent::where('agent_id', $id)->firstOrFail();

        $performances = Performance::where('agent_id', $id)
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get()
            ->map(function ($perf) {
                return [
                    'year' => $perf->year,
                    'month' => $perf->month,
                    'db_assigned_count' => $perf->db_assigned_count,
                    'contract_count' => $perf->contract_count,
                    'contract_amount' => (float) $perf->contract_amount,
                    'consultation_count' => $perf->consultation_count,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $performances,
        ]);
    }
}
