<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\BranchFilterable;
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
    use BranchFilterable;
    /**
     * 실적 요약 (SFR-043)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function summary(Request $request): JsonResponse
    {
        $period = $request->get('period', 'month');
        $branchId = $this->resolveBranchId($request);

        $startDate = match ($period) {
            'day' => Carbon::today(),
            'week' => Carbon::now()->startOfWeek(),
            default => Carbon::now()->startOfMonth(),
        };

        $assignmentQuery = CustomerAssignment::where('created_at', '>=', $startDate);
        $contractQuery = Contract::where('contract_date', '>=', $startDate);
        $contractAmountQuery = Contract::where('contract_date', '>=', $startDate);

        if ($branchId !== null) {
            $assignmentQuery->whereHas('agent.branches', fn($q) => $q->where('branch.branch_id', $branchId));
            $contractQuery->whereHas('customer.agent.branches', fn($q) => $q->where('branch.branch_id', $branchId));
            $contractAmountQuery->whereHas('customer.agent.branches', fn($q) => $q->where('branch.branch_id', $branchId));
        }

        $totalAssignments = $assignmentQuery->count();
        $totalContracts = $contractQuery->count();
        $totalContractAmount = $contractAmountQuery->sum('contract_amount');
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
        $branchId = $this->resolveBranchId($request);

        $query = Agent::where('is_active', true);
        $this->applyAgentBranchFilter($query, $branchId);

        $agents = $query->with(['performances' => function ($q) use ($now) {
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

    public function detailList(Request $request): JsonResponse
    {
        $type = $request->get('type', 'assignments');
        $period = $request->get('period', 'month');
        $branchId = $this->resolveBranchId($request);
        $perPage = min(max((int) $request->get('per_page', 20), 1), 100);

        $startDate = match ($period) {
            'day' => Carbon::today(),
            'week' => Carbon::now()->startOfWeek(),
            default => Carbon::now()->startOfMonth(),
        };

        if ($type === 'contracts') {
            $query = Contract::with(['agent:agent_id,name', 'customer:customer_id,name,phone', 'insuranceCompany:company_id,company_name'])
                ->where('contract_date', '>=', $startDate)
                ->orderByDesc('contract_date');

            if ($branchId !== null) {
                $query->whereHas('agent.branches', fn($q) => $q->where('branch.branch_id', $branchId));
            }

            $data = $query->paginate($perPage);
            $data->getCollection()->transform(fn($c) => [
                'id' => $c->contract_id,
                'agent_name' => $c->agent?->name ?? '-',
                'customer_name' => $c->customer?->name ?? $c->customer_name ?? '-',
                'customer_phone' => $c->customer?->phone ?? $c->customer_phone ?? '-',
                'company_name' => $c->insuranceCompany?->company_name ?? '-',
                'insurance_product' => $c->insurance_product ?? '-',
                'contract_amount' => (float) $c->contract_amount,
                'contract_date' => $c->contract_date?->format('Y-m-d'),
                'contract_status' => $c->contract_status,
            ]);
        } else {
            $query = CustomerAssignment::with(['agent:agent_id,name', 'customer:customer_id,name,phone'])
                ->where('created_at', '>=', $startDate)
                ->orderByDesc('created_at');

            if ($branchId !== null) {
                $query->whereHas('agent.branches', fn($q) => $q->where('branch.branch_id', $branchId));
            }

            $data = $query->paginate($perPage);
            $data->getCollection()->transform(fn($a) => [
                'id' => $a->assignment_id,
                'agent_name' => $a->agent?->name ?? '-',
                'customer_name' => $a->customer?->name ?? '-',
                'customer_phone' => $a->customer?->phone ?? '-',
                'assignment_type' => $a->assignment_type,
                'assignment_date' => $a->assignment_date?->format('Y-m-d'),
                'notes' => $a->notes,
                'created_at' => $a->created_at?->format('Y-m-d H:i'),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
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
