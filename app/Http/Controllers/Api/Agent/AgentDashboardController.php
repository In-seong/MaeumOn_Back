<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\Consultation;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\CustomerAssignment;
use App\Models\DisclosureObligation;
use App\Models\ClaimRequest;
use App\Models\InsuranceClaim;
use App\Models\Notice;
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
     * 설계사 대시보드 통합 조회
     */
    public function full(Request $request): JsonResponse
    {
        $agent = $request->user()->agent;
        $agentId = $agent->agent_id;
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();

        $branch = $agent->branches()->first();

        // 1. 오늘의 일정
        $todaySchedules = CalendarEvent::where('agent_id', $agentId)
            ->where('event_date', $today)
            ->with('customer:customer_id,name')
            ->orderBy('start_time')
            ->limit(10)
            ->get(['event_id', 'agent_id', 'customer_id', 'event_type', 'title', 'memo', 'event_date', 'start_time', 'end_time', 'is_completed']);

        // 2. 이달의 실적
        $contractsThisMonth = Contract::where('agent_id', $agentId)
            ->whereBetween('contract_date', [$monthStart, $monthEnd])
            ->get(['contract_id', 'contract_amount']);
        $contractsCount = $contractsThisMonth->count();
        $contractsAmount = (int) $contractsThisMonth->sum('contract_amount');

        $dbCount = CustomerAssignment::where('agent_id', $agentId)
            ->whereBetween('assignment_date', [$monthStart, $monthEnd])
            ->count();

        $conversionRate = $dbCount > 0 ? round(($contractsCount / $dbCount) * 100) : 0;

        $newCustomers = Customer::where('agent_id', $agentId)
            ->where('is_active', true)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->count();

        $branchRank = null;
        $branchTotal = null;
        if ($branch) {
            $branchAgentIds = $branch->agents()->pluck('agent.agent_id');
            $branchTotal = $branchAgentIds->count();

            $agentContractCounts = Contract::whereIn('agent_id', $branchAgentIds)
                ->whereBetween('contract_date', [$monthStart, $monthEnd])
                ->selectRaw('agent_id, COUNT(*) as cnt')
                ->groupBy('agent_id')
                ->orderByDesc('cnt')
                ->pluck('cnt', 'agent_id');

            $rank = 1;
            $found = false;
            foreach ($agentContractCounts as $aid => $cnt) {
                if ($aid === $agentId) {
                    $found = true;
                    break;
                }
                $rank++;
            }
            $branchRank = $found ? $rank : $branchTotal;
        }

        // 3. 체크리스트
        $unregisteredDb = ClaimRequest::where('assigned_agent_id', $agentId)
            ->where('status', 'assigned')
            ->count();

        $incompleteClaims = InsuranceClaim::where('agent_id', $agentId)
            ->whereIn('claim_status', ['draft', 'pending'])
            ->count();

        $pendingConsultations = Consultation::where('assignee_id', $agentId)
            ->where('consultation_status', 'pending')
            ->count();

        // 4. DB 배분 내역 (최근 10건)
        $dbDistributions = CustomerAssignment::where('agent_id', $agentId)
            ->with('customer:customer_id,name,phone,hospital')
            ->orderByDesc('assignment_date')
            ->limit(10)
            ->get()
            ->map(fn ($a) => [
                'assignment_id' => $a->assignment_id,
                'assignment_date' => $a->assignment_date?->format('Y-m-d'),
                'deadline' => $a->assignment_date ? $a->assignment_date->copy()->addDays(3)->format('Y-m-d') : null,
                'customer_name' => $a->customer->name ?? '-',
                'customer_phone' => $a->customer->phone ?? '-',
                'hospital' => $a->customer->hospital ?? '-',
                'customer_id' => $a->customer_id,
            ]);

        // 5. 고객 관리 알림 — 갱신예정 (30일 이내)
        $renewals = Contract::where('agent_id', $agentId)
            ->where('expiration_date', '>=', $today)
            ->where('expiration_date', '<=', $today->copy()->addDays(30))
            ->with('customer:customer_id,name')
            ->orderBy('expiration_date')
            ->limit(5)
            ->get()
            ->map(fn ($c) => [
                'type' => 'renewal',
                'customer_name' => $c->customer->name ?? '-',
                'customer_id' => $c->customer_id,
                'date' => $c->expiration_date?->format('Y-m-d'),
                'label' => $c->insurance_product ?? '보험 갱신',
            ]);

        // 5-2. 생일 (7일 이내)
        $birthdayDates = [];
        for ($i = 0; $i <= 7; $i++) {
            $birthdayDates[] = $today->copy()->addDays($i)->format('m-d');
        }
        $birthdays = Customer::where('agent_id', $agentId)
            ->where('is_active', true)
            ->whereNotNull('birth_date')
            ->whereRaw("DATE_FORMAT(birth_date, '%m-%d') IN (" . implode(',', array_fill(0, count($birthdayDates), '?')) . ")", $birthdayDates)
            ->select('customer_id', 'name', 'birth_date')
            ->limit(10)
            ->get()
            ->map(fn ($c) => [
                'type' => 'birthday',
                'customer_name' => $c->name,
                'customer_id' => $c->customer_id,
                'date' => $c->birth_date?->format('Y-m-d'),
                'label' => '생일',
            ]);

        // 6. 공지·교육
        $notices = Notice::where(function ($q) use ($branch) {
                $q->whereNull('branch_id');
                if ($branch) {
                    $q->orWhere('branch_id', $branch->branch_id);
                }
            })
            ->where(function ($q) use ($today) {
                $q->where(fn ($q2) => $q2->whereNull('display_start_date')->orWhere('display_start_date', '<=', $today));
                $q->where(fn ($q2) => $q2->whereNull('display_end_date')->orWhere('display_end_date', '>=', $today));
            })
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['notice_id', 'title', 'notice_type', 'is_pinned', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => [
                'header' => [
                    'agent_name' => $agent->name,
                    'branch_name' => $branch->branch_name ?? '',
                    'today' => $today->format('Y-m-d'),
                ],
                'today_schedules' => $todaySchedules,
                'performance' => [
                    'contracts_count' => $contractsCount,
                    'contracts_amount' => $contractsAmount,
                    'db_count' => $dbCount,
                    'conversion_rate' => $conversionRate,
                    'new_customers' => $newCustomers,
                    'branch_rank' => $branchRank,
                    'branch_total' => $branchTotal,
                ],
                'checklist' => [
                    'unregistered_db' => $unregisteredDb,
                    'incomplete_claims' => $incompleteClaims,
                    'pending_consultations' => $pendingConsultations,
                ],
                'db_distributions' => $dbDistributions,
                'customer_alerts' => $renewals->concat($birthdays)->sortBy('date')->values(),
                'notices' => $notices,
            ],
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

        // 3) DB배분 고객 (전체)
        $assignments = CustomerAssignment::where('agent_id', $agentId)
            ->with('customer:customer_id,name,phone')
            ->orderByDesc('assignment_date')
            ->limit(30)
            ->get();

        foreach ($assignments as $assignment) {
            $customerName = $assignment->customer->name ?? '미확인';
            $phone = $assignment->customer->phone ?? '';
            $date = $assignment->assignment_date
                ? Carbon::parse($assignment->assignment_date)->format('m/d')
                : '';
            $subtitle = $date;
            if ($phone) {
                $subtitle .= ($subtitle ? ' · ' : '') . $phone;
            }

            $tasks[] = [
                'id' => 'assignment_' . $assignment->assignment_id,
                'type' => 'assignment',
                'title' => $customerName,
                'subtitle' => $subtitle,
                'is_completed' => false,
                'can_toggle' => false,
                'related_id' => $assignment->assignment_id,
                'due_time' => null,
                'route' => '/customers/' . $assignment->customer_id,
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

        // 6) 청구 배정 (관리자가 배정한 청구신청)
        $claimRequests = ClaimRequest::where('assigned_agent_id', $agentId)
            ->where('status', 'assigned')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        foreach ($claimRequests as $cr) {
            $date = $cr->updated_at ? Carbon::parse($cr->updated_at)->format('m/d') : '';
            $subtitle = $date;
            if ($cr->phone) {
                $subtitle .= ($subtitle ? ' · ' : '') . $cr->phone;
            }

            $tasks[] = [
                'id' => 'claim_request_' . $cr->request_id,
                'type' => 'claim_request',
                'title' => $cr->name . ' 청구신청 배정',
                'subtitle' => $subtitle,
                'is_completed' => false,
                'can_toggle' => false,
                'related_id' => $cr->request_id,
                'due_time' => null,
                'route' => '/db-distribution',
            ];
        }

        // 시간순 정렬: due_time이 있는 것 먼저, 없는 것은 타입 우선순위
        $typePriority = [
            'obligation' => 1,
            'consultation' => 2,
            'claim_request' => 3,
            'claim' => 4,
            'calendar' => 5,
            'assignment' => 6,
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
            'birthday_alarm_enabled' => 'sometimes|boolean',
        ]);

        $agent->update($validated);

        return response()->json([
            'success' => true,
            'data' => $agent->fresh(),
            'message' => '프로필이 수정되었습니다.',
        ]);
    }
}
