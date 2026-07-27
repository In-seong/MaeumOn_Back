<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = SiteSetting::all()->keyBy('key');

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string|max:100',
            'settings.*.value' => 'required|max:500',
        ]);

        foreach ($validated['settings'] as $item) {
            SiteSetting::setValue($item['key'], $item['value']);
        }

        $settings = SiteSetting::all()->keyBy('key');

        return response()->json([
            'success' => true,
            'message' => '설정이 저장되었습니다.',
            'data' => $settings,
        ]);
    }
}
