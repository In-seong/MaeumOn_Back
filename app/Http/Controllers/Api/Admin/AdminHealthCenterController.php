<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\BranchFilterable;
use App\Models\HealthCenter;
use App\Models\HospitalAccount;
use App\Models\Traits\HasScheduleConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AdminHealthCenterController extends Controller
{
    use BranchFilterable;

    public function index(Request $request): JsonResponse
    {
        $query = HealthCenter::with(['accounts', 'images', 'branch:branch_id,branch_name'])
            ->where('is_deleted', true);

        $branchId = $this->resolveBranchId($request);
        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('center_name', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        // 정렬
        $sortField = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        if (in_array($sortField, ['created_at', 'center_name', 'is_active'])) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        }

        $centers = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $centers,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'center_name' => 'required|string|max:100',
            'address' => 'required|string|max:255',
            'detailed_address' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'business_hours' => 'nullable|string',
            'introduction' => 'nullable|string',
            'branch_id' => 'nullable|integer|exists:branch,branch_id',
            ...HealthCenter::scheduleConfigValidationRules(),
            'portal_username' => 'nullable|string|max:50|unique:hospital_account,username',
            'portal_password' => 'nullable|string|min:4',
        ]);

        $center = HealthCenter::create($validated);

        if (!empty($validated['portal_username']) && !empty($validated['portal_password'])) {
            HospitalAccount::create([
                'center_id' => $center->center_id,
                'username' => $validated['portal_username'],
                'password' => Hash::make($validated['portal_password']),
                'account_name' => $center->center_name,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $center,
            'message' => '건강검진 센터가 등록되었습니다.',
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $center = HealthCenter::with(['accounts', 'images', 'branch:branch_id,branch_name'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $center,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $center = HealthCenter::findOrFail($id);

        $validated = $request->validate([
            'center_name' => 'sometimes|string|max:100',
            'address' => 'sometimes|string|max:255',
            'detailed_address' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'business_hours' => 'nullable|string',
            'introduction' => 'nullable|string',
            'branch_id' => 'nullable|integer|exists:branch,branch_id',
            ...HealthCenter::scheduleConfigValidationRules(),
            'reservation_enabled' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'portal_username' => 'nullable|string|max:50',
            'portal_password' => 'nullable|string|min:4',
        ]);

        $center->update(collect($validated)->except(['portal_username', 'portal_password'])->toArray());

        // 포털 계정 생성 또는 수정
        if (!empty($validated['portal_username'])) {
            $account = $center->accounts()->first();
            if ($account) {
                $accountData = ['username' => $validated['portal_username']];
                if (!empty($validated['portal_password'])) {
                    $accountData['password'] = Hash::make($validated['portal_password']);
                }
                $account->update($accountData);
            } else {
                if (!empty($validated['portal_password'])) {
                    HospitalAccount::create([
                        'center_id' => $center->center_id,
                        'username' => $validated['portal_username'],
                        'password' => Hash::make($validated['portal_password']),
                        'account_name' => $center->center_name,
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $center->load('accounts'),
            'message' => '건강검진 센터 정보가 수정되었습니다.',
        ]);
    }

    public function addImage(Request $request, int $id): JsonResponse
    {
        $center = HealthCenter::findOrFail($id);

        $request->validate(['image' => 'required|image|max:5120']);

        $path = $request->file('image')->store("health-centers/{$id}", 's3');
        $maxOrder = $center->images()->max('sort_order') ?? -1;

        $image = \App\Models\HealthCenterImage::create([
            'center_id' => $id,
            'image_path' => $path,
            'sort_order' => $maxOrder + 1,
        ]);

        return response()->json(['success' => true, 'data' => $image], 201);
    }

    public function deleteImage(int $id, int $imageId): JsonResponse
    {
        $image = \App\Models\HealthCenterImage::where('center_id', $id)
            ->where('image_id', $imageId)
            ->firstOrFail();

        Storage::disk('s3')->delete($image->image_path);
        $image->delete();

        return response()->json(['success' => true, 'message' => '이미지가 삭제되었습니다.']);
    }

    public function uploadThumbnail(Request $request, int $id): JsonResponse
    {
        $center = HealthCenter::findOrFail($id);

        $request->validate([
            'thumbnail' => 'required|image|max:5120',
        ]);

        if ($center->thumbnail_path) {
            Storage::disk('s3')->delete($center->thumbnail_path);
        }

        $path = $request->file('thumbnail')->store("health-centers/{$id}/thumbnails", 's3');
        $center->update(['thumbnail_path' => $path]);

        return response()->json([
            'success' => true,
            'data' => $center,
            'message' => '썸네일이 업로드되었습니다.',
        ]);
    }

    public function deleteThumbnail(int $id): JsonResponse
    {
        $center = HealthCenter::findOrFail($id);

        if ($center->thumbnail_path) {
            Storage::disk('s3')->delete($center->thumbnail_path);
            $center->update(['thumbnail_path' => null]);
        }

        return response()->json([
            'success' => true,
            'message' => '썸네일이 삭제되었습니다.',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $center = HealthCenter::findOrFail($id);
        $center->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => '건강검진 센터가 비활성화되었습니다.',
        ]);
    }

    public function forceDelete(int $id): JsonResponse
    {
        $center = HealthCenter::findOrFail($id);
        $center->update(['is_active' => false, 'is_deleted' => false]);
        $center->accounts()->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => '건강검진 센터가 삭제되었습니다.',
        ]);
    }
}
