<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\Reminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentScheduleController extends Controller
{
    /**
     * 일정 목록 조회 (날짜 범위 필터)
     */
    public function index(Request $request): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $query = CalendarEvent::where('agent_id', $agentId)
            ->with('customer:customer_id,name,phone');

        // 날짜 범위 필터
        if ($request->has('from') && $request->has('to')) {
            $query->whereBetween('event_date', [$request->from, $request->to]);
        }

        // 이벤트 유형 필터
        if ($request->has('event_type')) {
            $query->where('event_type', $request->event_type);
        }

        // 소스 필터 (manual / system)
        if ($request->has('source')) {
            $query->where('source', $request->source);
        }

        $query->orderBy('event_date', 'asc')->orderBy('start_time', 'asc');

        $perPage = min(max((int) $request->get('per_page', 50), 1), 100);
        $events = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    /**
     * 월간 일정 (날짜별 그룹핑, 캘린더 마킹용)
     */
    public function month(Request $request, int $year, int $month): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $from = sprintf('%04d-%02d-01', $year, $month);
        $to = date('Y-m-t', strtotime($from));

        $events = CalendarEvent::where('agent_id', $agentId)
            ->whereBetween('event_date', [$from, $to])
            ->with('customer:customer_id,name,phone')
            ->orderBy('event_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        // 날짜별 그룹핑
        $grouped = [];
        foreach ($events as $event) {
            $dateKey = $event->event_date->format('Y-m-d');
            $grouped[$dateKey][] = $event;
        }

        return response()->json([
            'success' => true,
            'data' => $grouped,
        ]);
    }

    /**
     * 다가오는 주요 일정 (대시보드 위젯용)
     */
    public function upcoming(Request $request): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;
        $days = min(max((int) $request->get('days', 30), 1), 90);

        $today = now()->toDateString();
        $until = now()->addDays($days)->toDateString();

        $events = CalendarEvent::where('agent_id', $agentId)
            ->whereBetween('event_date', [$today, $until])
            ->where('is_completed', false)
            ->with('customer:customer_id,name,phone')
            ->orderBy('event_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    /**
     * 일정 상세
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $event = CalendarEvent::where('agent_id', $agentId)
            ->with(['customer:customer_id,name,phone', 'contract.insuranceCompany:company_id,company_name', 'reminders'])
            ->where('event_id', $id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $event,
        ]);
    }

    /**
     * 일정 생성
     */
    public function store(Request $request): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $validated = $request->validate([
            'customer_id' => 'nullable|string|exists:customer,customer_id',
            'contract_id' => 'nullable|integer|exists:contract,contract_id',
            'event_type' => 'required|string|max:30',
            'title' => 'required|string|max:200',
            'memo' => 'nullable|string',
            'event_date' => 'required|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'is_all_day' => 'nullable|boolean',
            'reminder_days' => 'nullable|array',
            'reminder_days.*' => 'integer|min:0|max:90',
        ]);

        $event = CalendarEvent::create([
            'agent_id' => $agentId,
            'customer_id' => $validated['customer_id'] ?? null,
            'contract_id' => $validated['contract_id'] ?? null,
            'event_type' => $validated['event_type'],
            'title' => $validated['title'],
            'memo' => $validated['memo'] ?? null,
            'event_date' => $validated['event_date'],
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'is_all_day' => $validated['is_all_day'] ?? ($validated['start_time'] ? false : true),
            'source' => CalendarEvent::SOURCE_MANUAL,
        ]);

        // 리마인더 생성
        $reminderDays = $validated['reminder_days'] ?? [1]; // 기본 D-1
        foreach ($reminderDays as $days) {
            Reminder::create([
                'event_id' => $event->event_id,
                'agent_id' => $agentId,
                'remind_before_days' => $days,
            ]);
        }

        $event->load(['customer:customer_id,name,phone', 'reminders']);

        return response()->json([
            'success' => true,
            'data' => $event,
            'message' => '일정이 등록되었습니다.',
        ], 201);
    }

    /**
     * 일정 수정
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $event = CalendarEvent::where('agent_id', $agentId)
            ->where('event_id', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'customer_id' => 'nullable|string|exists:customer,customer_id',
            'event_type' => 'sometimes|string|max:30',
            'title' => 'sometimes|string|max:200',
            'memo' => 'nullable|string',
            'event_date' => 'sometimes|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'is_all_day' => 'nullable|boolean',
            'is_completed' => 'nullable|boolean',
            'reminder_days' => 'nullable|array',
            'reminder_days.*' => 'integer|min:0|max:90',
        ]);

        $event->update($validated);

        // 리마인더 재설정 (제공된 경우)
        if (array_key_exists('reminder_days', $validated)) {
            $event->reminders()->delete();
            $reminderDays = $validated['reminder_days'] ?? [];
            foreach ($reminderDays as $days) {
                Reminder::create([
                    'event_id' => $event->event_id,
                    'agent_id' => $agentId,
                    'remind_before_days' => $days,
                ]);
            }
        }

        $event->load(['customer:customer_id,name,phone', 'reminders']);

        return response()->json([
            'success' => true,
            'data' => $event,
            'message' => '일정이 수정되었습니다.',
        ]);
    }

    /**
     * 일정 삭제
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $event = CalendarEvent::where('agent_id', $agentId)
            ->where('event_id', $id)
            ->firstOrFail();

        $event->reminders()->delete();
        $event->delete();

        return response()->json([
            'success' => true,
            'message' => '일정이 삭제되었습니다.',
        ]);
    }

    /**
     * 완료/미완료 토글
     */
    public function toggleComplete(Request $request, int $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $event = CalendarEvent::where('agent_id', $agentId)
            ->where('event_id', $id)
            ->firstOrFail();

        $event->update(['is_completed' => !$event->is_completed]);

        return response()->json([
            'success' => true,
            'data' => $event,
        ]);
    }
}
