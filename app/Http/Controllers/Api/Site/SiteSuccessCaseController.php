<?php

namespace App\Http\Controllers\Api\Site;

use App\Http\Controllers\Controller;
use App\Models\SiteSuccessCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteSuccessCaseController extends Controller
{
    /**
     * 공개 목록 (홈·사례 페이지)
     * GET /api/success-cases?limit=8
     */
    public function index(Request $request): JsonResponse
    {
        $limit = min((int) $request->get('limit', 0), 1000) ?: null;

        $query = SiteSuccessCase::where('is_deleted', 0)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $items = $limit ? $query->limit($limit)->get() : $query->get();

        return response()->json([
            'items' => $items->map->toApiFormat()->values(),
        ]);
    }

    /**
     * 공개 상세
     * GET /api/success-cases/{id}
     */
    public function show(string $id): JsonResponse
    {
        if (!ctype_digit($id)) {
            return response()->json(['error' => '사례를 찾을 수 없습니다.'], 404);
        }

        $case = SiteSuccessCase::where('id', $id)->where('is_deleted', 0)->first();

        if (!$case) {
            return response()->json(['error' => '사례를 찾을 수 없습니다.'], 404);
        }

        return response()->json($case->toApiFormat());
    }

    /**
     * 관리자 목록 (페이지네이션)
     * GET /api/admin/success-cases?page=1&perPage=10
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->get('page', 1));
        $perPage = min(max(1, (int) $request->get('perPage', 10)), 100);

        $query = SiteSuccessCase::where('is_deleted', 0)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $total = $query->count();
        $totalPages = max(1, (int) ceil($total / $perPage));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $items = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response()->json([
            'items' => $items->map->toApiFormat()->values(),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
        ]);
    }

    /**
     * 관리자 등록
     * POST /api/admin/success-cases
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'keywords' => 'nullable|array|max:4',
            'keywords.*' => 'string|max:50',
            'summary' => 'nullable|string|max:500',
            'detail' => 'nullable|string',
            'imageUrl' => 'nullable|string|max:500',
            'author' => 'nullable|array',
            'author.id' => 'nullable|integer',
            'author.name' => 'nullable|string|max:50',
        ]);

        $keywords = null;
        if (!empty($data['keywords'])) {
            $cleaned = array_map(fn($k) => str_replace(',', ' ', trim($k)), $data['keywords']);
            $keywords = implode(',', array_filter($cleaned));
        }

        $case = SiteSuccessCase::create([
            'title' => $data['title'],
            'keywords' => $keywords ?: null,
            'summary' => $data['summary'] ?? null,
            'detail' => $data['detail'] ?? null,
            'image_url' => $data['imageUrl'] ?? null,
            'author_id' => $data['author']['id'] ?? null,
            'author_name' => $data['author']['name'] ?? null,
        ]);

        return response()->json($case->fresh()->toApiFormat(), 201);
    }

    /**
     * 관리자 수정
     * PUT /api/admin/success-cases/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $case = SiteSuccessCase::where('id', $id)->where('is_deleted', 0)->first();

        if (!$case) {
            return response()->json(['error' => '사례를 찾을 수 없습니다.'], 404);
        }

        $data = $request->validate([
            'title' => 'sometimes|required|string|max:200',
            'keywords' => 'nullable|array|max:4',
            'keywords.*' => 'string|max:50',
            'summary' => 'nullable|string|max:500',
            'detail' => 'nullable|string',
            'imageUrl' => 'nullable|string|max:500',
        ]);

        $updates = [];
        if (array_key_exists('title', $data)) $updates['title'] = $data['title'];
        if (array_key_exists('summary', $data)) $updates['summary'] = $data['summary'];
        if (array_key_exists('detail', $data)) $updates['detail'] = $data['detail'];
        if (array_key_exists('imageUrl', $data)) $updates['image_url'] = $data['imageUrl'];

        if (array_key_exists('keywords', $data)) {
            if (!empty($data['keywords'])) {
                $cleaned = array_map(fn($k) => str_replace(',', ' ', trim($k)), $data['keywords']);
                $updates['keywords'] = implode(',', array_filter($cleaned));
            } else {
                $updates['keywords'] = null;
            }
        }

        $case->update($updates);

        return response()->json($case->fresh()->toApiFormat());
    }

    /**
     * 관리자 삭제 (소프트)
     * DELETE /api/admin/success-cases/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $case = SiteSuccessCase::where('id', $id)->where('is_deleted', 0)->first();

        if (!$case) {
            return response()->json(['error' => '사례를 찾을 수 없습니다.'], 404);
        }

        $case->update([
            'is_deleted' => 1,
            'deleted_at' => now(),
        ]);

        return response()->json(null, 204);
    }
}
