<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Memo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentMemoController extends Controller
{
    /**
     * 고객 메모 목록 조회
     */
    public function index(Request $request, string $customerId): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        // 해당 고객이 이 설계사에게 소속되어 있는지 확인
        Customer::where('agent_id', $agentId)
            ->where('customer_id', $customerId)
            ->firstOrFail();

        $memos = Memo::where('customer_id', $customerId)
            ->orderBy('memo_date', 'desc')
            ->paginate(min(max((int) $request->get('per_page', 15), 1), 100));

        return response()->json([
            'success' => true,
            'data' => $memos,
        ]);
    }

    /**
     * 메모 생성
     */
    public function store(Request $request, string $customerId): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        // 해당 고객이 이 설계사에게 소속되어 있는지 확인
        Customer::where('agent_id', $agentId)
            ->where('customer_id', $customerId)
            ->firstOrFail();

        $validated = $request->validate([
            'title' => 'nullable|string|max:200',
            'content' => 'required|string',
            'memo_date' => 'required|date',
        ]);

        $memo = Memo::create(array_merge($validated, [
            'customer_id' => $customerId,
            'author_id' => $agentId,
            'author_type' => 'AGENT',
        ]));

        return response()->json([
            'success' => true,
            'data' => $memo,
            'message' => '메모가 등록되었습니다.',
        ], 201);
    }

    /**
     * 메모 수정
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $memo = Memo::findOrFail($id);

        // 메모의 고객이 이 설계사 소속인지 확인
        Customer::where('agent_id', $agentId)
            ->where('customer_id', $memo->customer_id)
            ->firstOrFail();

        // 메모 작성자가 본인인지 확인
        if ($memo->author_id !== $agentId || $memo->author_type !== 'AGENT') {
            return response()->json([
                'success' => false,
                'message' => '본인이 작성한 메모만 수정할 수 있습니다.',
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:200',
            'content' => 'sometimes|required|string',
            'memo_date' => 'sometimes|required|date',
        ]);

        $memo->update($validated);

        return response()->json([
            'success' => true,
            'data' => $memo->fresh(),
            'message' => '메모가 수정되었습니다.',
        ]);
    }

    /**
     * 메모 삭제
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $memo = Memo::findOrFail($id);

        // 메모의 고객이 이 설계사 소속인지 확인
        Customer::where('agent_id', $agentId)
            ->where('customer_id', $memo->customer_id)
            ->firstOrFail();

        // 메모 작성자가 본인인지 확인
        if ($memo->author_id !== $agentId || $memo->author_type !== 'AGENT') {
            return response()->json([
                'success' => false,
                'message' => '본인이 작성한 메모만 삭제할 수 있습니다.',
            ], 403);
        }

        $memo->delete();

        return response()->json([
            'success' => true,
            'message' => '메모가 삭제되었습니다.',
        ]);
    }
}
