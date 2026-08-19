<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\BranchFilterable;
use App\Models\Customer;
use App\Models\Agent;
use App\Models\InsuranceClaim;
use App\Models\CustomerAssignment;
use App\Models\Notice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    use BranchFilterable;

    /**
     * 관리자 대시보드 요약 데이터
     */
    public function index(Request $request): JsonResponse
    {
        $branchId = $this->resolveBranchId($request);

        $customerQuery = Customer::where('is_active', true);
        $agentQuery = Agent::where('is_active', true);
        $claimQuery = InsuranceClaim::query();
        $pendingClaimQuery = InsuranceClaim::where('claim_status', 'pending');
        $assignmentQuery = CustomerAssignment::query();

        if ($branchId !== null) {
            $customerQuery->whereHas('agent.branches', fn($q) => $q->where('branch.branch_id', $branchId));
            $agentQuery->whereHas('branches', fn($q) => $q->where('branch.branch_id', $branchId));
            $claimQuery->whereHas('customer.agent.branches', fn($q) => $q->where('branch.branch_id', $branchId));
            $pendingClaimQuery->whereHas('customer.agent.branches', fn($q) => $q->where('branch.branch_id', $branchId));
            $assignmentQuery->whereHas('agent.branches', fn($q) => $q->where('branch.branch_id', $branchId));
        }

        $totalCustomers = $customerQuery->count();
        $totalAgents = $agentQuery->count();
        $totalClaims = $claimQuery->count();
        $pendingClaims = $pendingClaimQuery->count();
        $totalAssignments = $assignmentQuery->count();

        $noticeQuery = Notice::with('author:admin_id,name');
        if ($branchId !== null) {
            $noticeQuery->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }
        $recentNotices = $noticeQuery
            ->orderBy('is_pinned', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_customers' => $totalCustomers,
                'total_agents' => $totalAgents,
                'total_claims' => $totalClaims,
                'pending_claims' => $pendingClaims,
                'total_assignments' => $totalAssignments,
                'recent_notices' => $recentNotices,
            ],
        ]);
    }
}
