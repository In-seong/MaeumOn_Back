<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminMaintenanceTaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = MaintenanceTask::query();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_direction', 'desc');
        $allowed = ['id', 'title', 'priority', 'status', 'source', 'created_at', 'updated_at'];
        if (in_array($sortBy, $allowed, true)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $tasks = $query->paginate($request->input('per_page', 100));

        return response()->json(['success' => true, 'data' => $tasks]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'priority' => 'required|in:P0,P1,P2,P3',
            'status' => 'required|in:요청,진행중,완료,테스트완료,테스트실패,보류',
            'source' => 'nullable|in:카톡,회의,직접입력,기타',
        ]);

        $task = MaintenanceTask::create($validated);

        return response()->json(['success' => true, 'data' => $task], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $task = MaintenanceTask::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'priority' => 'sometimes|required|in:P0,P1,P2,P3',
            'status' => 'sometimes|required|in:요청,진행중,완료,테스트완료,테스트실패,보류',
            'source' => 'nullable|in:카톡,회의,직접입력,기타',
        ]);

        if (isset($validated['status']) && in_array($validated['status'], ['완료', '테스트완료'])) {
            $validated['completed_at'] = now();
        } elseif (isset($validated['status']) && !in_array($validated['status'], ['완료', '테스트완료'])) {
            $validated['completed_at'] = null;
        }

        $task->update($validated);

        return response()->json(['success' => true, 'data' => $task->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $task = MaintenanceTask::findOrFail($id);
        $task->delete();

        return response()->json(['success' => true, 'message' => '삭제되었습니다.']);
    }
}
