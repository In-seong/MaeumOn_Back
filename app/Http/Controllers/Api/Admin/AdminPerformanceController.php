<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\BranchFilterable;
use App\Models\Agent;
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
        $branchId = $this->resolveBranchId($request);

        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        if ($dateFrom && $dateTo) {
            $startDate = Carbon::parse($dateFrom)->startOfDay();
            $endDate = Carbon::parse($dateTo)->endOfDay();
        } else {
            $period = $request->get('period', 'month');
            $startDate = match ($period) {
                'day' => Carbon::today(),
                'week' => Carbon::now()->startOfWeek(),
                default => Carbon::now()->startOfMonth(),
            };
            $endDate = Carbon::now()->endOfDay();
        }

        $assignmentQuery = CustomerAssignment::whereBetween('created_at', [$startDate, $endDate]);
        $contractQuery = Contract::whereBetween('contract_date', [$startDate, $endDate]);
        $contractAmountQuery = Contract::whereBetween('contract_date', [$startDate, $endDate]);

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

        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth()->endOfDay();

        $query = Agent::where('is_active', true);
        $this->applyAgentBranchFilter($query, $branchId);

        $agents = $query->withCount(['customers', 'contracts'])->paginate($perPage);

        $agentIds = $agents->getCollection()->pluck('agent_id')->toArray();

        $contractStats = collect();
        $assignmentCounts = collect();

        if (!empty($agentIds)) {
            $contractStats = DB::table('contract')
                ->selectRaw('agent_id, COUNT(*) as contract_count, COALESCE(SUM(contract_amount), 0) as contract_amount')
                ->whereBetween('contract_date', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
                ->whereIn('agent_id', $agentIds)
                ->groupBy('agent_id')
                ->get()
                ->keyBy('agent_id');

            $assignmentCounts = DB::table('customer_assignment')
                ->selectRaw('agent_id, COUNT(*) as cnt')
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->whereIn('agent_id', $agentIds)
                ->groupBy('agent_id')
                ->pluck('cnt', 'agent_id');
        }

        $agents->getCollection()->transform(function ($agent) use ($contractStats, $assignmentCounts) {
            $cs = $contractStats->get($agent->agent_id);
            $contractCount = $cs ? (int) $cs->contract_count : 0;
            $contractAmount = $cs ? (float) $cs->contract_amount : 0;
            $dbAssigned = (int) ($assignmentCounts->get($agent->agent_id) ?? 0);

            return [
                'agent_id' => $agent->agent_id,
                'agent_name' => $agent->name,
                'db_assigned_count' => $dbAssigned,
                'contract_count' => $contractCount,
                'contract_amount' => $contractAmount,
                'consultation_count' => 0,
                'processing_rate' => $dbAssigned > 0
                    ? round(($contractCount / $dbAssigned) * 100, 1)
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
        $branchId = $this->resolveBranchId($request);
        $perPage = min(max((int) $request->get('per_page', 20), 1), 100);

        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        if ($dateFrom && $dateTo) {
            $startDate = Carbon::parse($dateFrom)->startOfDay();
            $endDate = Carbon::parse($dateTo)->endOfDay();
        } else {
            $period = $request->get('period', 'month');
            $startDate = match ($period) {
                'day' => Carbon::today(),
                'week' => Carbon::now()->startOfWeek(),
                default => Carbon::now()->startOfMonth(),
            };
            $endDate = Carbon::now()->endOfDay();
        }

        if ($type === 'contracts') {
            $query = Contract::with(['agent:agent_id,name', 'customer:customer_id,name,phone', 'insuranceCompany:company_id,company_name'])
                ->whereBetween('contract_date', [$startDate, $endDate])
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
                ->whereBetween('created_at', [$startDate, $endDate])
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
        Agent::where('agent_id', $id)->firstOrFail();

        $now = Carbon::now();
        $twelveMonthsAgo = $now->copy()->subMonths(11)->startOfMonth();

        $contractsByMonth = DB::table('contract')
            ->selectRaw('YEAR(contract_date) as year, MONTH(contract_date) as month, COUNT(*) as contract_count, COALESCE(SUM(contract_amount), 0) as contract_amount')
            ->where('agent_id', $id)
            ->where('contract_date', '>=', $twelveMonthsAgo->format('Y-m-d'))
            ->groupByRaw('YEAR(contract_date), MONTH(contract_date)')
            ->get()
            ->keyBy(fn ($r) => $r->year . '-' . $r->month);

        $assignmentsByMonth = DB::table('customer_assignment')
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as cnt')
            ->where('agent_id', $id)
            ->where('created_at', '>=', $twelveMonthsAgo)
            ->groupByRaw('YEAR(created_at), MONTH(created_at)')
            ->get()
            ->keyBy(fn ($r) => $r->year . '-' . $r->month);

        $performances = [];
        for ($i = 0; $i < 12; $i++) {
            $date = $now->copy()->subMonths($i);
            $key = $date->year . '-' . $date->month;
            $cs = $contractsByMonth->get($key);
            $as = $assignmentsByMonth->get($key);

            $performances[] = [
                'year' => $date->year,
                'month' => $date->month,
                'db_assigned_count' => $as ? (int) $as->cnt : 0,
                'contract_count' => $cs ? (int) $cs->contract_count : 0,
                'contract_amount' => $cs ? (float) $cs->contract_amount : 0,
                'consultation_count' => 0,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $performances,
        ]);
    }
}
