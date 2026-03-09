<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConsentTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminConsentTemplateController extends Controller
{
    /**
     * 동의서 목록 조회
     */
    public function index(): JsonResponse
    {
        $templates = ConsentTemplate::orderByRaw("FIELD(consent_type, 'unique_id', 'sensitive', 'credit')")->get();

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    /**
     * 동의서 수정
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $template = ConsentTemplate::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:100',
            'content' => 'required|string',
        ]);

        $template->update($validated);

        return response()->json([
            'success' => true,
            'data' => $template,
            'message' => '동의서가 수정되었습니다.',
        ]);
    }
}
