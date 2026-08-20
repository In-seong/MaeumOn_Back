<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\DistributionQueue;
use App\Services\DistributionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentDistributionController extends Controller
{
    /**
     * 내게 배분된 미확인 건 목록
     */
    public function pending(Request $request): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $items = DistributionQueue::with('customer:customer_id,name,phone')
            ->where('assigned_agent_id', $agentId)
            ->where('status', 'assigned')
            ->whereNull('viewed_at')
            ->orderBy('assigned_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    /**
     * 배분 확인 처리
     */
    public function confirm(Request $request, int $queueId, DistributionService $service): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $result = $service->confirmDistribution($agentId, $queueId);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => '확인할 배분 건을 찾을 수 없습니다.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => '배분을 확인했습니다.',
        ]);
    }
}
