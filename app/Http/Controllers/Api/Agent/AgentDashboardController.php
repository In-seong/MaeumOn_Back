<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Consultation;
use App\Models\Customer;
use App\Models\CustomerAssignment;
use App\Models\DisclosureObligation;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentDashboardController extends Controller
{
    /**
     * 대시보드 요약 정보
     */
    public function index(Request $request): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $totalCustomers = Customer::where('agent_id', $agentId)
            ->where('is_active', true)
            ->count();

        $pendingConsultations = Consultation::where('assignee_id', $agentId)
            ->where('status', 'pending')
            ->count();

        $unreadNotifications = Notification::where('receiver_id', $agentId)
            ->where('receiver_type', 'AGENT')
            ->where('is_read', false)
            ->count();

        $recentAssignments = CustomerAssignment::where('agent_id', $agentId)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $upcomingObligations = DisclosureObligation::whereHas('customer', function ($query) use ($agentId) {
            $query->where('agent_id', $agentId);
        })
            ->where('obligation_end_date', '>=', now())
            ->where('obligation_end_date', '<=', now()->addDays(30))
            ->where('obligation_status', '!=', 'completed')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_customers' => $totalCustomers,
                'pending_consultations' => $pendingConsultations,
                'unread_notifications' => $unreadNotifications,
                'recent_assignments' => $recentAssignments,
                'upcoming_obligations' => $upcomingObligations,
            ],
            'message' => '대시보드 정보를 조회했습니다.',
        ]);
    }

    /**
     * 설계사 프로필 조회
     */
    public function profile(Request $request): JsonResponse
    {
        $agent = $request->user()->agent;
        $agent->load('account:account_id,username,role,is_active,last_login_at');

        return response()->json([
            'success' => true,
            'data' => $agent,
        ]);
    }

    /**
     * 설계사 프로필 수정
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $agent = $request->user()->agent;

        $validated = $request->validate([
            'phone' => 'sometimes|string|max:20',
            'email' => 'sometimes|email|max:100',
            'specialization' => 'sometimes|nullable|string|max:100',
            'profile_image_url' => 'sometimes|nullable|string|max:500',
        ]);

        $agent->update($validated);

        return response()->json([
            'success' => true,
            'data' => $agent->fresh(),
            'message' => '프로필이 수정되었습니다.',
        ]);
    }
}
