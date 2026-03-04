<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Agent;
use App\Models\InsuranceClaim;
use App\Models\CustomerAssignment;
use App\Models\Notice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    /**
     * 관리자 대시보드 요약 데이터
     */
    public function index(Request $request): JsonResponse
    {
        $totalCustomers = Customer::where('is_active', true)->count();
        $totalAgents = Agent::where('is_active', true)->count();
        $totalClaims = InsuranceClaim::count();
        $pendingClaims = InsuranceClaim::where('claim_status', 'pending')->count();
        $totalAssignments = CustomerAssignment::count();

        $recentNotices = Notice::with('author:admin_id,name')
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
