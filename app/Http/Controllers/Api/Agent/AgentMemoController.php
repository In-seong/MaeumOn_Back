<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Memo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            'files' => 'nullable|array|max:5',
            'files.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,pdf,heic,heif',
        ]);

        $attachments = $this->uploadFiles($request, $customerId);

        $memo = Memo::create([
            'customer_id' => $customerId,
            'author_id' => $agentId,
            'author_type' => 'AGENT',
            'title' => $validated['title'] ?? null,
            'content' => $validated['content'],
            'memo_date' => $validated['memo_date'],
            'attachments' => !empty($attachments) ? $attachments : null,
        ]);

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
            'files' => 'nullable|array|max:5',
            'files.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,pdf,heic,heif',
            'remove_attachments' => 'nullable|array',
            'remove_attachments.*' => 'string',
        ]);

        $updateData = collect($validated)->only(['title', 'content', 'memo_date'])->toArray();

        $currentAttachments = $memo->attachments ?? [];

        if (!empty($validated['remove_attachments'])) {
            foreach ($validated['remove_attachments'] as $path) {
                Storage::disk('s3')->delete($path);
                $currentAttachments = array_values(array_filter($currentAttachments, fn($a) => $a !== $path));
            }
        }

        $newFiles = $this->uploadFiles($request, $memo->customer_id);
        $currentAttachments = array_merge($currentAttachments, $newFiles);

        $updateData['attachments'] = !empty($currentAttachments) ? array_values($currentAttachments) : null;

        $memo->update($updateData);

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

        if (!empty($memo->attachments)) {
            foreach ($memo->attachments as $path) {
                Storage::disk('s3')->delete($path);
            }
        }

        $memo->delete();

        return response()->json([
            'success' => true,
            'message' => '메모가 삭제되었습니다.',
        ]);
    }

    private function uploadFiles(Request $request, string $customerId): array
    {
        $paths = [];
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $paths[] = $file->store("memos/{$customerId}", 's3');
            }
        }
        return $paths;
    }
}
