<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\BranchFilterable;
use App\Models\CustomerAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminDistributionStatisticsController extends Controller
{
    use BranchFilterable;

    public function index(Request $request): JsonResponse
    {
        $period = $request->get('period', 'month');
        $branchId = $this->resolveBranchId($request);

        $startDate = match ($period) {
            'day' => Carbon::today(),
            'week' => Carbon::now()->startOfWeek(),
            default => Carbon::now()->startOfMonth(),
        };

        $query = CustomerAssignment::select(
                'customer_assignment.agent_id',
                DB::raw("SUM(CASE WHEN customer_assignment.assignment_type = 'NEW' THEN 1 ELSE 0 END) as resident_count"),
                DB::raw("SUM(CASE WHEN customer_assignment.assignment_type IN ('auto_distribute','auto_timeout_reassign') THEN 1 ELSE 0 END) as distribution_count"),
                DB::raw('COUNT(*) as total_count')
            )
            ->join('agent', 'customer_assignment.agent_id', '=', 'agent.agent_id')
            ->where('customer_assignment.created_at', '>=', $startDate)
            ->groupBy('customer_assignment.agent_id');

        if ($branchId !== null) {
            $query->whereExists(function ($sub) use ($branchId) {
                $sub->select(DB::raw(1))
                    ->from('agent_branch')
                    ->whereColumn('agent_branch.agent_id', 'customer_assignment.agent_id')
                    ->where('agent_branch.branch_id', $branchId);
            });
        }

        $rows = $query->get();

        $agentIds = $rows->pluck('agent_id')->toArray();
        $agents = \App\Models\Agent::whereIn('agent_id', $agentIds)
            ->get()
            ->keyBy('agent_id');

        $data = $rows->map(function ($row) use ($agents) {
            $agent = $agents->get($row->agent_id);
            return [
                'agent_id' => $row->agent_id,
                'agent_name' => $agent?->name ?? '-',
                'resident_count' => (int) $row->resident_count,
                'distribution_count' => (int) $row->distribution_count,
                'total_count' => (int) $row->total_count,
            ];
        })->sortByDesc('total_count')->values();

        $summary = [
            'total_resident' => $data->sum('resident_count'),
            'total_distribution' => $data->sum('distribution_count'),
            'total_all' => $data->sum('total_count'),
            'agent_count' => $data->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'agents' => $data,
            ],
        ]);
    }
}
