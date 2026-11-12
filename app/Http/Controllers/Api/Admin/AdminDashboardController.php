<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\BranchFilterable;
use App\Models\Agent;
use App\Models\Consultation;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\CustomerAssignment;
use App\Models\InsuranceClaim;
use App\Models\Notice;
use App\Models\PartnerHospital;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    use BranchFilterable;

    public function index(Request $request): JsonResponse
    {
        $branchId = $this->resolveBranchId($request);

        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $data = [
            'kpi' => $this->getKpiCards($branchId, $start, $end),
            'action_items' => $this->getActionItems($branchId),
            'agent_ranking' => $this->getAgentRanking($branchId, $start, $end),
            'recent_activities' => $this->getRecentActivities($branchId),
            'recent_notices' => $this->getRecentNotices($branchId),
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    private function getKpiCards(?int $branchId, Carbon $start, Carbon $end): array
    {
        $contractQuery = Contract::whereBetween('contract_date', [$start->format('Y-m-d'), $end->format('Y-m-d')]);
        $newCustomerQuery = Customer::whereNull('deleted_at')
            ->whereBetween('created_at', [$start, $end]);
        $agentQuery = Agent::where('is_active', true)->whereNull('deleted_at');
        $newAgentQuery = Agent::where('is_active', true)->whereNull('deleted_at')
            ->whereBetween('created_at', [$start, $end]);
        $totalCustomerQuery = Customer::whereNull('deleted_at');
        $hospitalQuery = PartnerHospital::where('is_active', true);
        $newHospitalQuery = PartnerHospital::where('is_active', true)
            ->whereBetween('created_at', [$start, $end]);
        $apiLogQuery = DB::table('codef_api_logs')
            ->whereBetween('created_at', [$start, $end]);
        $claimQuery = InsuranceClaim::whereBetween('created_at', [$start, $end]);

        if ($branchId !== null) {
            $contractQuery->whereHas('agent.branches', fn($q) => $q->where('branch.branch_id', $branchId));
            $newCustomerQuery->whereHas('agent.branches', fn($q) => $q->where('branch.branch_id', $branchId));
            $agentQuery->whereHas('branches', fn($q) => $q->where('branch.branch_id', $branchId));
            $newAgentQuery->whereHas('branches', fn($q) => $q->where('branch.branch_id', $branchId));
            $totalCustomerQuery->whereHas('agent.branches', fn($q) => $q->where('branch.branch_id', $branchId));
            $hospitalQuery->where('branch_id', $branchId);
            $newHospitalQuery->where('branch_id', $branchId);
            $apiLogQuery->whereIn('agent_id', function ($q) use ($branchId) {
                $q->select('agent_id')->from('agent_branch')->where('branch_id', $branchId);
            });
            $claimQuery->whereHas('agent.branches', fn($q) => $q->where('branch.branch_id', $branchId));
        }

        $contractCount = $contractQuery->count();
        $contractAmount = (int) $contractQuery->sum('contract_amount');

        $newDbCount = $newCustomerQuery->count();

        $conversionRate = $newDbCount > 0
            ? round(($contractCount / $newDbCount) * 100, 1)
            : 0;

        $assignedCustomerCount = CustomerAssignment::whereBetween('created_at', [$start, $end])->count();
        $assignRate = $newDbCount > 0
            ? round(($assignedCustomerCount / $newDbCount) * 100, 0)
            : 0;

        return [
            'contract_amount' => $contractAmount,
            'contract_count' => $contractCount,
            'db_count' => $newDbCount,
            'conversion_rate' => $conversionRate,
            'new_db_count' => $newDbCount,
            'assign_complete_rate' => $assignRate,
            'active_agents' => $agentQuery->count(),
            'new_agents' => $newAgentQuery->count(),
            'total_customers' => $totalCustomerQuery->count(),
            'new_customers' => (int) Customer::whereNull('deleted_at')->whereBetween('created_at', [$start, $end])->count(),
            'hospitals' => $hospitalQuery->count(),
            'new_hospitals' => $newHospitalQuery->count(),
            'api_usage' => $apiLogQuery->count(),
            'claim_count' => $claimQuery->count(),
        ];
    }

    private function getActionItems(?int $branchId): array
    {
        $unassignedDb = DB::table('distribution_queue')
            ->where('status', 'pending')
            ->count();

        $cutoff = Carbon::now()->subHours(24);
        $noContactDb = DB::table('distribution_queue')
            ->whereNotNull('assigned_agent_id')
            ->whereNull('viewed_at')
            ->where('assigned_at', '<', $cutoff)
            ->count();

        $pendingReservations = DB::table('hospital_reservation')
            ->where('status', 'pending')
            ->count();

        $unansweredQna = Consultation::where('consultation_status', 'pending')->count();

        return [
            'unassigned_db' => $unassignedDb,
            'no_contact_db' => $noContactDb,
            'pending_reservations' => $pendingReservations,
            'unanswered_qna' => $unansweredQna,
        ];
    }

    private function getAgentRanking(?int $branchId, Carbon $start, Carbon $end): array
    {
        $agentQuery = Agent::where('is_active', true)->whereNull('deleted_at');
        if ($branchId !== null) {
            $agentQuery->whereHas('branches', fn($q) => $q->where('branch.branch_id', $branchId));
        }
        $totalAgents = $agentQuery->count();

        $startStr = $start->format('Y-m-d H:i:s');
        $endStr = $end->format('Y-m-d H:i:s');

        $agentStats = DB::select("
            SELECT
                a.agent_id,
                a.name,
                COALESCE(c.contract_count, 0) as contract_count,
                COALESCE(d.db_count, 0) as db_count,
                COALESCE(api.api_count, 0) as api_count,
                COALESCE(cl.claim_count, 0) as claim_count
            FROM agent a
            LEFT JOIN (
                SELECT agent_id, COUNT(*) as contract_count
                FROM contract
                WHERE contract_date BETWEEN ? AND ?
                GROUP BY agent_id
            ) c ON a.agent_id = c.agent_id
            LEFT JOIN (
                SELECT assigned_agent_id as agent_id, COUNT(*) as db_count
                FROM distribution_queue
                WHERE created_at BETWEEN ? AND ?
                GROUP BY assigned_agent_id
            ) d ON a.agent_id = d.agent_id
            LEFT JOIN (
                SELECT agent_id, COUNT(*) as api_count
                FROM codef_api_logs
                WHERE created_at BETWEEN ? AND ?
                GROUP BY agent_id
            ) api ON a.agent_id = api.agent_id
            LEFT JOIN (
                SELECT agent_id, COUNT(*) as claim_count
                FROM insurance_claim
                WHERE created_at BETWEEN ? AND ?
                GROUP BY agent_id
            ) cl ON a.agent_id = cl.agent_id
            WHERE a.is_active = 1 AND a.deleted_at IS NULL
            " . ($branchId !== null ? "AND a.agent_id IN (SELECT agent_id FROM agent_branch WHERE branch_id = ?)" : "") . "
            HAVING (contract_count + db_count + api_count + claim_count) > 0
            ORDER BY (COALESCE(c.contract_count,0)*10 + COALESCE(cl.claim_count,0)*3 + COALESCE(api.api_count,0)) DESC
        ", $branchId !== null
            ? [$start->format('Y-m-d'), $end->format('Y-m-d'), $startStr, $endStr, $startStr, $endStr, $startStr, $endStr, $branchId]
            : [$start->format('Y-m-d'), $end->format('Y-m-d'), $startStr, $endStr, $startStr, $endStr, $startStr, $endStr]
        );

        $top = [];
        $needsCare = [];

        foreach ($agentStats as $agent) {
            $rate = $agent->db_count > 0
                ? round(($agent->contract_count / $agent->db_count) * 100, 0)
                : 0;

            $item = [
                'agent_id' => $agent->agent_id,
                'name' => $agent->name,
                'contract_count' => (int) $agent->contract_count,
                'db_count' => (int) $agent->db_count,
                'conversion_rate' => $rate,
                'api_count' => (int) $agent->api_count,
                'claim_count' => (int) $agent->claim_count,
            ];

            if ($rate < 30 && $agent->db_count >= 5) {
                $needsCare[] = $item;
            }

            $top[] = $item;
        }

        usort($needsCare, fn($a, $b) => $a['conversion_rate'] <=> $b['conversion_rate']);

        return [
            'total_agents' => $totalAgents,
            'top' => array_slice($top, 0, 5),
            'needs_care' => array_slice($needsCare, 0, 3),
        ];
    }

    private function getRecentActivities(?int $branchId): array
    {
        $activities = collect();

        $claims = InsuranceClaim::with('agent:agent_id,name', 'customer:customer_id,name')
            ->whereIn('claim_status', ['paid', 'pending', 'approved', 'submitted'])
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($claim) {
                $customerName = $claim->customer?->name ?? '고객';
                $maskedName = mb_substr($customerName, 0, 1) . '*' . mb_substr($customerName, -1);
                $agentName = $claim->agent?->name ?? '설계사';

                if ($claim->claim_status === 'paid') {
                    $type = 'paid';
                    $label = '지급완료';
                    $desc = "{$agentName} · {$maskedName} 고객 청구 지급 확인";
                } else {
                    $type = 'claim';
                    $label = '청구접수';
                    $desc = "{$agentName} · {$maskedName} 고객 청구 접수";
                }

                return [
                    'type' => $type,
                    'label' => $label,
                    'description' => $desc,
                    'time' => $claim->updated_at->format('H:i'),
                    'date' => $claim->updated_at->format('Y-m-d'),
                    'timestamp' => $claim->updated_at->timestamp,
                ];
            });
        $activities = $activities->merge($claims);

        $contracts = Contract::with('agent:agent_id,name', 'customer:customer_id,name')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($contract) {
                $customerName = $contract->customer?->name ?? '고객';
                $maskedName = mb_substr($customerName, 0, 1) . '*' . mb_substr($customerName, -1);
                $agentName = $contract->agent?->name ?? '설계사';
                $product = $contract->insurance_product ?? '보험';

                return [
                    'type' => 'contract',
                    'label' => '계약',
                    'description' => "{$agentName} · {$maskedName} 고객 {$product} 신규 체결",
                    'time' => $contract->created_at->format('H:i'),
                    'date' => $contract->created_at->format('Y-m-d'),
                    'timestamp' => $contract->created_at->timestamp,
                ];
            });
        $activities = $activities->merge($contracts);

        $distributions = DB::table('distribution_queue')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($dist) {
                return [
                    'type' => 'distribution',
                    'label' => 'DB배분',
                    'description' => '신규 DB 배분 처리',
                    'time' => Carbon::parse($dist->created_at)->format('H:i'),
                    'date' => Carbon::parse($dist->created_at)->format('Y-m-d'),
                    'timestamp' => Carbon::parse($dist->created_at)->timestamp,
                ];
            });
        $activities = $activities->merge($distributions);

        return $activities
            ->sortByDesc('timestamp')
            ->take(10)
            ->values()
            ->toArray();
    }

    private function getRecentNotices(?int $branchId): array
    {
        $query = Notice::with('author:admin_id,name');
        if ($branchId !== null) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }

        return $query
            ->orderBy('is_pinned', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
    }
}
