<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminBannerController extends Controller
{
    public function index(): JsonResponse
    {
        $banners = Banner::orderBy('sort_order')->orderByDesc('banner_id')->get();
        return response()->json(['data' => $banners]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:100',
            'image' => 'required|image|max:5120',
            'link_url' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $path = $request->file('image')->store('banners', 's3');

        $banner = Banner::create([
            'title' => $request->title,
            'image_path' => $path,
            'link_url' => $request->link_url,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json(['data' => $banner], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $banner = Banner::findOrFail($id);

        $request->validate([
            'title' => 'sometimes|string|max:100',
            'image' => 'sometimes|image|max:5120',
            'link_url' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if ($request->hasFile('image')) {
            if ($banner->image_path) {
                Storage::disk('s3')->delete($banner->image_path);
            }
            $banner->image_path = $request->file('image')->store('banners', 's3');
        }

        if ($request->has('title')) $banner->title = $request->title;
        if ($request->has('link_url')) $banner->link_url = $request->link_url;
        if ($request->has('sort_order')) $banner->sort_order = $request->sort_order;
        if ($request->has('is_active')) $banner->is_active = $request->boolean('is_active');

        $banner->save();

        return response()->json(['data' => $banner]);
    }

    public function destroy(int $id): JsonResponse
    {
        $banner = Banner::findOrFail($id);

        if ($banner->image_path) {
            Storage::disk('s3')->delete($banner->image_path);
        }

        $banner->delete();

        return response()->json(['message' => '삭제되었습니다.']);
    }
}
