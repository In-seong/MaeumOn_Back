<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\BranchFilterable;
use App\Models\DistributionConfig;
use App\Models\DistributionList;
use App\Models\DistributionListItem;
use App\Models\DistributionQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDistributionController extends Controller
{
    use BranchFilterable;

    /**
     * 지사별 자동배분 설정 조회
     */
    public function getConfig(Request $request): JsonResponse
    {
        $branchId = $this->resolveBranchId($request);

        $query = DistributionConfig::with(['branch:branch_id,branch_name', 'currentList.items.agent:agent_id,name']);

        if ($branchId) {
            $config = $query->where('branch_id', $branchId)->first();
            return response()->json(['success' => true, 'data' => $config]);
        }

        $configs = $query->get();
        return response()->json(['success' => true, 'data' => $configs]);
    }

    /**
     * 자동배분 on/off 토글
     */
    public function toggleActive(Request $request, int $branchId): JsonResponse
    {
        $config = DistributionConfig::firstOrCreate(
            ['branch_id' => $branchId],
            ['is_active' => false, 'current_position' => 0]
        );

        $newActive = !$config->is_active;

        $resumeInfo = null;
        if ($newActive && $config->current_list_id) {
            $list = DistributionList::with('items')->find($config->current_list_id);

            if ($list) {
                $currentHash = $list->computeContentHash();
                $listChanged = $currentHash !== $list->content_hash;

                if ($listChanged) {
                    $config->current_position = 0;
                    $list->updateContentHash();
                    $resumeInfo = [
                        'list_changed' => true,
                        'message' => '리스트가 변경되어 1번부터 시작합니다.',
                    ];
                } else {
                    $totalAgents = $list->items->count();
                    $nextPosition = $config->current_position;
                    $nextItem = $list->items->firstWhere('position', $nextPosition + 1);

                    $resumeInfo = [
                        'list_changed' => false,
                        'last_position' => $config->current_position,
                        'next_position' => $nextPosition + 1,
                        'next_agent' => $nextItem?->agent?->name ?? null,
                        'total_agents' => $totalAgents,
                        'message' => "{$config->current_position}번까지 배분했습니다. " . ($nextPosition + 1) . "번부터 시작합니다.",
                    ];
                }
            }
        }

        if ($newActive && !$config->current_list_id) {
            return response()->json([
                'success' => false,
                'message' => '활성화하려면 먼저 배분 순서 리스트를 설정해주세요.',
            ], 422);
        }

        $updateData = ['is_active' => $newActive];
        if ($newActive && $resumeInfo && ($resumeInfo['list_changed'] ?? false)) {
            $updateData['current_position'] = 0;
        }
        $config->update($updateData);

        return response()->json([
            'success' => true,
            'data' => [
                'is_active' => $newActive,
                'resume_info' => $resumeInfo,
            ],
            'message' => $newActive ? '자동배분이 활성화되었습니다.' : '자동배분이 비활성화되었습니다.',
        ]);
    }

    /**
     * 회기 위치 수동 설정 (on 시 시작 위치 변경)
     */
    public function setPosition(Request $request, int $branchId): JsonResponse
    {
        $validated = $request->validate([
            'position' => 'required|integer|min:0',
        ]);

        $config = DistributionConfig::where('branch_id', $branchId)->firstOrFail();
        $config->update(['current_position' => $validated['position']]);

        return response()->json([
            'success' => true,
            'message' => ($validated['position'] + 1) . '번부터 배분을 시작합니다.',
        ]);
    }

    /**
     * 배분 순서 리스트 목록 (지사별)
     */
    public function getLists(Request $request, int $branchId): JsonResponse
    {
        $lists = DistributionList::where('branch_id', $branchId)
            ->withCount('items')
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $lists]);
    }

    /**
     * 배분 순서 리스트 상세 (설계사 포함)
     */
    public function getList(int $branchId, int $listId): JsonResponse
    {
        $list = DistributionList::with(['items.agent:agent_id,name,employee_number,phone'])
            ->where('branch_id', $branchId)
            ->where('list_id', $listId)
            ->firstOrFail();

        return response()->json(['success' => true, 'data' => $list]);
    }

    /**
     * 배분 순서 리스트 생성/저장
     */
    public function saveList(Request $request, int $branchId): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'agent_ids' => 'required|array|min:1',
            'agent_ids.*' => 'string|exists:agent,agent_id',
        ]);

        return DB::transaction(function () use ($validated, $branchId) {
            $list = DistributionList::create([
                'branch_id' => $branchId,
                'name' => $validated['name'],
                'content_hash' => '',
            ]);

            foreach ($validated['agent_ids'] as $index => $agentId) {
                DistributionListItem::create([
                    'list_id' => $list->list_id,
                    'agent_id' => $agentId,
                    'position' => $index + 1,
                    'created_at' => now(),
                ]);
            }

            $list->updateContentHash();

            return response()->json([
                'success' => true,
                'data' => $list->load('items.agent:agent_id,name'),
                'message' => '배분 리스트가 저장되었습니다.',
            ], 201);
        });
    }

    /**
     * 배분 순서 리스트 수정
     */
    public function updateList(Request $request, int $branchId, int $listId): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'agent_ids' => 'sometimes|array|min:1',
            'agent_ids.*' => 'string|exists:agent,agent_id',
        ]);

        $list = DistributionList::where('branch_id', $branchId)
            ->where('list_id', $listId)
            ->firstOrFail();

        return DB::transaction(function () use ($validated, $list) {
            if (isset($validated['name'])) {
                $list->update(['name' => $validated['name']]);
            }

            if (isset($validated['agent_ids'])) {
                $list->items()->delete();

                foreach ($validated['agent_ids'] as $index => $agentId) {
                    DistributionListItem::create([
                        'list_id' => $list->list_id,
                        'agent_id' => $agentId,
                        'position' => $index + 1,
                        'created_at' => now(),
                    ]);
                }

                $list->updateContentHash();
            }

            return response()->json([
                'success' => true,
                'data' => $list->fresh()->load('items.agent:agent_id,name'),
                'message' => '배분 리스트가 수정되었습니다.',
            ]);
        });
    }

    /**
     * 배분 순서 리스트 삭제
     */
    public function deleteList(int $branchId, int $listId): JsonResponse
    {
        $list = DistributionList::where('branch_id', $branchId)
            ->where('list_id', $listId)
            ->firstOrFail();

        $activeConfig = DistributionConfig::where('current_list_id', $listId)
            ->where('is_active', true)
            ->first();

        if ($activeConfig) {
            return response()->json([
                'success' => false,
                'message' => '현재 사용 중인 리스트는 삭제할 수 없습니다. 먼저 비활성화해주세요.',
            ], 422);
        }

        DistributionConfig::where('current_list_id', $listId)
            ->update(['current_list_id' => null]);

        $list->delete();

        return response()->json([
            'success' => true,
            'message' => '배분 리스트가 삭제되었습니다.',
        ]);
    }

    /**
     * 리스트를 현재 배분용으로 설정
     */
    public function activateList(Request $request, int $branchId, int $listId): JsonResponse
    {
        $list = DistributionList::where('branch_id', $branchId)
            ->where('list_id', $listId)
            ->firstOrFail();

        $config = DistributionConfig::firstOrCreate(
            ['branch_id' => $branchId],
            ['is_active' => false, 'current_position' => 0]
        );

        $config->update([
            'current_list_id' => $listId,
            'current_position' => 0,
        ]);

        $list->updateContentHash();

        return response()->json([
            'success' => true,
            'message' => "'{$list->name}' 리스트가 배분용으로 설정되었습니다.",
        ]);
    }

    /**
     * 대기열 현황 조회
     */
    public function getQueue(Request $request): JsonResponse
    {
        $branchId = $this->resolveBranchId($request);

        $query = DistributionQueue::with([
            'customer:customer_id,name,phone',
            'assignedAgent:agent_id,name',
        ]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $queue = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json(['success' => true, 'data' => $queue]);
    }
}
