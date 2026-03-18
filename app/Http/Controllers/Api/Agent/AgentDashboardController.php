<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\Consultation;
use App\Models\Customer;
use App\Models\CustomerAssignment;
use App\Models\DisclosureObligation;
use App\Models\InsuranceClaim;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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
            ->where('consultation_status', 'pending')
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
            ->where('tracking_end_date', '>=', now())
            ->where('tracking_end_date', '<=', now()->addDays(30))
            ->where('is_disclosed', false)
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
     * 오늘의 할일 통합 조회
     */
    public function todayTasks(Request $request): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;
        $today = Carbon::today();
        $tasks = [];

        // 1) 캘린더 일정 (오늘 미완료)
        $events = CalendarEvent::where('agent_id', $agentId)
            ->where('event_date', $today)
            ->where('is_completed', false)
            ->with('customer:customer_id,name')
            ->orderBy('start_time')
            ->limit(10)
            ->get();

        foreach ($events as $event) {
            $subtitle = '';
            if ($event->start_time) {
                $subtitle = substr($event->start_time, 0, 5);
                if ($event->end_time) {
                    $subtitle .= ' ~ ' . substr($event->end_time, 0, 5);
                }
            }
            if ($event->customer) {
                $subtitle .= ($subtitle ? ' · ' : '') . $event->customer->name;
            }

            $tasks[] = [
                'id' => 'calendar_' . $event->event_id,
                'type' => 'calendar',
                'title' => $event->title,
                'subtitle' => $subtitle,
                'is_completed' => false,
                'can_toggle' => true,
                'related_id' => $event->event_id,
                'due_time' => $event->start_time ? substr($event->start_time, 0, 5) : null,
                'route' => '/schedule',
            ];
        }

        // 2) 상담 요청 (오늘 미처리)
        $consultations = Consultation::where('assignee_id', $agentId)
            ->where('assignee_type', 'AGENT')
            ->whereDate('consultation_date', $today)
            ->whereIn('consultation_status', ['pending', 'in_progress'])
            ->with('customer:customer_id,name')
            ->orderBy('consultation_date')
            ->limit(10)
            ->get();

        foreach ($consultations as $consult) {
            $time = $consult->consultation_date
                ? Carbon::parse($consult->consultation_date)->format('H:i')
                : null;

            $tasks[] = [
                'id' => 'consultation_' . $consult->consultation_id,
                'type' => 'consultation',
                'title' => ($consult->customer->name ?? '고객') . ' 상담 요청',
                'subtitle' => $consult->consultation_type . ($time ? " ({$time})" : ''),
                'is_completed' => false,
                'can_toggle' => false,
                'related_id' => $consult->consultation_id,
                'due_time' => $time,
                'route' => '/consultations',
            ];
        }

        // 3) DB배분 신규 고객 (오늘 배분)
        $assignments = CustomerAssignment::where('agent_id', $agentId)
            ->where('assignment_date', $today)
            ->with('customer:customer_id,name')
            ->limit(10)
            ->get();

        if ($assignments->count() > 0) {
            $names = $assignments->map(fn($a) => $a->customer->name ?? '미확인')->take(3)->join(', ');
            $count = $assignments->count();

            $tasks[] = [
                'id' => 'assignment_group_' . $today->format('Ymd'),
                'type' => 'assignment',
                'title' => "DB배분 신규 고객 {$count}명",
                'subtitle' => $names . ($count > 3 ? " 외 " . ($count - 3) . "명" : ''),
                'is_completed' => false,
                'can_toggle' => false,
                'related_id' => null,
                'due_time' => null,
                'route' => '/db-distribution',
            ];
        }

        // 4) 보험 청구 미처리 (오늘 접수)
        $claims = InsuranceClaim::where('agent_id', $agentId)
            ->where('claim_date', $today)
            ->whereIn('claim_status', ['pending', 'processing'])
            ->with('customer:customer_id,name')
            ->select(['claim_id', 'customer_id', 'agent_id', 'claim_status', 'claim_date', 'claim_type', 'created_at', 'updated_at'])
            ->limit(10)
            ->get()
            ->each(fn($c) => $c->setAppends([]));

        foreach ($claims as $claim) {
            $tasks[] = [
                'id' => 'claim_' . $claim->claim_id,
                'type' => 'claim',
                'title' => ($claim->customer->name ?? '고객') . ' 청구서류 확인',
                'subtitle' => $claim->claim_status === 'pending' ? '접수 대기' : '처리 중',
                'is_completed' => false,
                'can_toggle' => false,
                'related_id' => $claim->claim_id,
                'due_time' => null,
                'route' => '/claims/' . $claim->claim_id,
            ];
        }

        // 5) 알릴의무 마감 (오늘 또는 7일 이내)
        $obligations = DisclosureObligation::whereHas('customer', function ($query) use ($agentId) {
            $query->where('agent_id', $agentId);
        })
            ->where('is_disclosed', false)
            ->where('tracking_end_date', '>=', $today)
            ->where('tracking_end_date', '<=', $today->copy()->addDays(7))
            ->with('customer:customer_id,name')
            ->orderBy('tracking_end_date')
            ->limit(10)
            ->get();

        foreach ($obligations as $obligation) {
            $daysLeft = $today->diffInDays(Carbon::parse($obligation->tracking_end_date), false);
            $urgency = $daysLeft === 0 ? '오늘 마감' : "D-{$daysLeft}";

            $tasks[] = [
                'id' => 'obligation_' . $obligation->disclosure_id,
                'type' => 'obligation',
                'title' => ($obligation->customer->name ?? '고객') . ' 알릴의무 확인',
                'subtitle' => $urgency,
                'is_completed' => false,
                'can_toggle' => true,
                'related_id' => $obligation->disclosure_id,
                'due_time' => null,
                'route' => '/alert-duty',
            ];
        }

        // 시간순 정렬: due_time이 있는 것 먼저, 없는 것은 타입 우선순위
        $typePriority = [
            'obligation' => 1,
            'consultation' => 2,
            'claim' => 3,
            'calendar' => 4,
            'assignment' => 5,
        ];

        usort($tasks, function ($a, $b) use ($typePriority) {
            // due_time이 있는 것 우선
            $aHasTime = !empty($a['due_time']);
            $bHasTime = !empty($b['due_time']);

            if ($aHasTime && !$bHasTime) return -1;
            if (!$aHasTime && $bHasTime) return 1;
            if ($aHasTime && $bHasTime) return strcmp($a['due_time'], $b['due_time']);

            // 둘 다 시간 없으면 타입 우선순위
            return ($typePriority[$a['type']] ?? 99) - ($typePriority[$b['type']] ?? 99);
        });

        // 최대 10개
        $tasks = array_slice($tasks, 0, 10);

        return response()->json([
            'success' => true,
            'data' => $tasks,
            'message' => '오늘의 할일을 조회했습니다.',
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
            'office_location' => 'sometimes|nullable|string|max:100',
            'specialization' => 'sometimes|nullable|string|max:200',
        ]);

        $agent->update($validated);

        return response()->json([
            'success' => true,
            'data' => $agent->fresh(),
            'message' => '프로필이 수정되었습니다.',
        ]);
    }
}
