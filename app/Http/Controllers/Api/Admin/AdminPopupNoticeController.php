<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PopupNotice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminPopupNoticeController extends Controller
{
    public function index(): JsonResponse
    {
        $notices = PopupNotice::orderBy('sort_order')
            ->orderByDesc('popup_notice_id')
            ->get();

        return response()->json(['data' => $notices]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:100',
            'image' => 'required|image|max:5120',
            'link_url' => 'nullable|string|max:500',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $path = $request->file('image')->store('popup-notices', 's3');

        $notice = PopupNotice::create([
            'title' => $request->title,
            'image_path' => $path,
            'link_url' => $request->link_url,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json(['data' => $notice], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $notice = PopupNotice::findOrFail($id);

        $request->validate([
            'title' => 'sometimes|string|max:100',
            'image' => 'sometimes|image|max:5120',
            'link_url' => 'nullable|string|max:500',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if ($request->hasFile('image')) {
            if ($notice->image_path) {
                Storage::disk('s3')->delete($notice->image_path);
            }
            $notice->image_path = $request->file('image')->store('popup-notices', 's3');
        }

        if ($request->has('title')) $notice->title = $request->title;
        if ($request->has('link_url')) $notice->link_url = $request->link_url;
        if ($request->has('start_date')) $notice->start_date = $request->start_date;
        if ($request->has('end_date')) $notice->end_date = $request->end_date;
        if ($request->has('sort_order')) $notice->sort_order = $request->sort_order;
        if ($request->has('is_active')) $notice->is_active = $request->boolean('is_active');

        $notice->save();

        return response()->json(['data' => $notice]);
    }

    public function destroy(int $id): JsonResponse
    {
        $notice = PopupNotice::findOrFail($id);

        if ($notice->image_path) {
            Storage::disk('s3')->delete($notice->image_path);
        }

        $notice->delete();

        return response()->json(['message' => '삭제되었습니다.']);
    }
}
