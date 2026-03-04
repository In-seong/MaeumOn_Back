<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class StandardFieldController extends Controller
{
    /**
     * 표준 필드 목록 (카테고리별 그룹핑)
     */
    public function index(): JsonResponse
    {
        $fields = config('standard_fields', []);

        // 카테고리별 그룹핑
        $grouped = [];
        foreach ($fields as $field) {
            $category = $field['category'];
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $field;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'fields' => $fields,
                'grouped' => $grouped,
            ],
        ]);
    }
}
