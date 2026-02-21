<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SatisfactionSurvey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentSatisfactionController extends Controller
{
    /**
     * 만족도 설문 목록 조회
     */
    public function index(Request $request): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $query = SatisfactionSurvey::where('agent_id', $agentId);

        if ($request->filled('survey_status')) {
            $query->where('survey_status', $request->get('survey_status'));
        }

        $surveys = $query->with('customer:customer_id,name,phone')
            ->orderBy('created_at', 'desc')
            ->paginate(min(max((int) $request->get('per_page', 15), 1), 100));

        return response()->json([
            'success' => true,
            'data' => $surveys,
            'message' => '만족도 설문 목록을 조회했습니다.',
        ]);
    }

    /**
     * 만족도 설문 발송
     */
    public function store(Request $request): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $validated = $request->validate([
            'customer_id' => 'required|exists:customer,customer_id',
            'survey_title' => 'required|string|max:200',
            'survey_content' => 'required|string',
        ]);

        // Verify customer belongs to this agent
        $customer = Customer::where('customer_id', $validated['customer_id'])
            ->where('agent_id', $agentId)
            ->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => '해당 고객은 담당 고객이 아닙니다.',
            ], 403);
        }

        $survey = SatisfactionSurvey::create([
            'agent_id' => $agentId,
            'customer_id' => $validated['customer_id'],
            'survey_title' => $validated['survey_title'],
            'survey_content' => $validated['survey_content'],
            'survey_status' => 'sent',
            'sent_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $survey,
            'message' => '만족도 설문을 발송했습니다.',
        ], 201);
    }

    /**
     * 만족도 설문 상세 조회
     */
    public function show(Request $request, $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $survey = SatisfactionSurvey::where('survey_id', $id)
            ->where('agent_id', $agentId)
            ->with('customer')
            ->first();

        if (!$survey) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => '설문을 찾을 수 없습니다.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $survey,
            'message' => '설문 상세 정보를 조회했습니다.',
        ]);
    }
}
