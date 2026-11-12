<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }
        if ($request->filled('target_month')) {
            $query->where('target_month', $request->input('target_month'));
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_direction', 'desc');
        $allowed = ['id', 'title', 'priority', 'status', 'source', 'category', 'target_month', 'created_at', 'updated_at'];
        if (in_array($sortBy, $allowed, true)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $tasks = $query->paginate($request->input('per_page', 100));

        $tasks->getCollection()->transform(function ($task) {
            if (!empty($task->images)) {
                $task->image_urls = array_map(fn ($path) => $this->toSignedUrl($path), $task->images);
            }
            return $task;
        });

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
            'category' => 'required|in:설계사,관리자,사용자,복합,기타',
            'target_month' => 'nullable|string|max:7',
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
            'category' => 'sometimes|required|in:설계사,관리자,사용자,복합,기타',
            'target_month' => 'nullable|string|max:7',
        ]);

        if (isset($validated['status']) && in_array($validated['status'], ['완료', '테스트완료'])) {
            $validated['completed_at'] = now();
        } elseif (isset($validated['status']) && !in_array($validated['status'], ['완료', '테스트완료'])) {
            $validated['completed_at'] = null;
        }

        $task->update($validated);

        return response()->json(['success' => true, 'data' => $task->fresh()]);
    }

    public function uploadImage(Request $request, int $id): JsonResponse
    {
        $task = MaintenanceTask::findOrFail($id);

        $request->validate([
            'image' => 'required|image|max:5120',
        ]);

        $path = $request->file('image')->store('maintenance-tasks', 's3');

        $images = $task->images ?? [];
        $images[] = $path;
        $task->update(['images' => $images]);

        $imageUrls = array_map(fn ($p) => $this->toSignedUrl($p), $images);

        return response()->json(['success' => true, 'data' => ['url' => $this->toSignedUrl($path), 'images' => $images, 'image_urls' => $imageUrls]]);
    }

    public function deleteImage(Request $request, int $id): JsonResponse
    {
        $task = MaintenanceTask::findOrFail($id);

        $request->validate(['path' => 'required|string']);

        $path = $request->input('path');
        $images = $task->images ?? [];
        $images = array_values(array_filter($images, fn ($img) => $img !== $path));
        $task->update(['images' => $images]);

        Storage::disk('s3')->delete($path);

        $imageUrls = array_map(fn ($p) => $this->toSignedUrl($p), $images);

        return response()->json(['success' => true, 'data' => ['images' => $images, 'image_urls' => $imageUrls]]);
    }

    private function toSignedUrl(string $pathOrUrl): string
    {
        if (str_starts_with($pathOrUrl, 'http')) {
            $parsed = parse_url($pathOrUrl, PHP_URL_PATH);
            $pathOrUrl = $parsed ? ltrim($parsed, '/') : $pathOrUrl;
        }
        return Storage::disk('s3')->temporaryUrl($pathOrUrl, now()->addHours(2));
    }

    public function destroy(int $id): JsonResponse
    {
        $task = MaintenanceTask::findOrFail($id);
        $task->delete();

        return response()->json(['success' => true, 'message' => '삭제되었습니다.']);
    }
}
