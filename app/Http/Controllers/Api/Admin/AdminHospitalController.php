<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PartnerHospital;
use App\Models\HospitalAccount;
use App\Models\Traits\HasScheduleConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AdminHospitalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PartnerHospital::with(['accounts', 'images'])
            ->where('is_deleted', true);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('hospital_name', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        // 정렬
        $sortField = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        if (in_array($sortField, ['created_at', 'hospital_name', 'is_active'])) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        }

        $hospitals = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $hospitals,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hospital_name' => 'required|string|max:100',
            'address' => 'required|string|max:255',
            'detailed_address' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'business_hours' => 'nullable|string',
            'introduction' => 'nullable|string',
            'specialties' => 'nullable|string',
            ...PartnerHospital::scheduleConfigValidationRules(),
            'portal_username' => 'nullable|string|max:50|unique:hospital_account,username',
            'portal_password' => 'nullable|string|min:4',
        ]);

        $hospital = PartnerHospital::create($validated);

        // 포털 계정 생성 (요청 시)
        if (!empty($validated['portal_username']) && !empty($validated['portal_password'])) {
            HospitalAccount::create([
                'hospital_id' => $hospital->hospital_id,
                'username' => $validated['portal_username'],
                'password' => Hash::make($validated['portal_password']),
                'account_name' => $hospital->hospital_name,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $hospital,
            'message' => '병원이 등록되었습니다.',
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $hospital = PartnerHospital::with(['accounts', 'images'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $hospital,
        ]);
    }

    public function addImage(Request $request, int $id): JsonResponse
    {
        $hospital = PartnerHospital::findOrFail($id);

        $request->validate([
            'image' => 'required|image|max:5120',
        ]);

        $path = $request->file('image')->store("hospitals/{$id}", 's3');
        $maxOrder = $hospital->images()->max('sort_order') ?? -1;

        $image = \App\Models\HospitalImage::create([
            'hospital_id' => $id,
            'image_path' => $path,
            'sort_order' => $maxOrder + 1,
        ]);

        return response()->json(['success' => true, 'data' => $image], 201);
    }

    public function deleteImage(int $id, int $imageId): JsonResponse
    {
        $image = \App\Models\HospitalImage::where('hospital_id', $id)
            ->where('image_id', $imageId)
            ->firstOrFail();

        Storage::disk('s3')->delete($image->image_path);
        $image->delete();

        return response()->json(['success' => true, 'message' => '이미지가 삭제되었습니다.']);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $hospital = PartnerHospital::findOrFail($id);

        $validated = $request->validate([
            'hospital_name' => 'sometimes|string|max:100',
            'address' => 'sometimes|string|max:255',
            'detailed_address' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'business_hours' => 'nullable|string',
            'introduction' => 'nullable|string',
            'specialties' => 'nullable|string',
            ...PartnerHospital::scheduleConfigValidationRules(),
            'reservation_enabled' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'portal_username' => 'nullable|string|max:50',
            'portal_password' => 'nullable|string|min:4',
        ]);

        $hospital->update(collect($validated)->except(['portal_username', 'portal_password'])->toArray());

        // 포털 계정 생성 또는 수정
        if (!empty($validated['portal_username'])) {
            $account = $hospital->accounts()->first();
            if ($account) {
                $accountData = ['username' => $validated['portal_username']];
                if (!empty($validated['portal_password'])) {
                    $accountData['password'] = Hash::make($validated['portal_password']);
                }
                $account->update($accountData);
            } else {
                if (!empty($validated['portal_password'])) {
                    HospitalAccount::create([
                        'hospital_id' => $hospital->hospital_id,
                        'username' => $validated['portal_username'],
                        'password' => Hash::make($validated['portal_password']),
                        'account_name' => $hospital->hospital_name,
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $hospital->load('accounts'),
            'message' => '병원 정보가 수정되었습니다.',
        ]);
    }

    public function uploadImage(Request $request, int $id): JsonResponse
    {
        $hospital = PartnerHospital::findOrFail($id);

        $request->validate([
            'image' => 'required|image|max:5120',
        ]);

        if ($hospital->image_path) {
            Storage::disk('s3')->delete($hospital->image_path);
        }

        $path = $request->file('image')->store("hospitals/{$id}", 's3');
        $hospital->update(['image_path' => $path]);

        return response()->json([
            'success' => true,
            'data' => $hospital,
            'message' => '이미지가 업로드되었습니다.',
        ]);
    }

    public function uploadThumbnail(Request $request, int $id): JsonResponse
    {
        $hospital = PartnerHospital::findOrFail($id);

        $request->validate([
            'thumbnail' => 'required|image|max:5120',
        ]);

        if ($hospital->thumbnail_path) {
            Storage::disk('s3')->delete($hospital->thumbnail_path);
        }

        $path = $request->file('thumbnail')->store("hospitals/{$id}/thumbnails", 's3');
        $hospital->update(['thumbnail_path' => $path]);

        return response()->json([
            'success' => true,
            'data' => $hospital,
            'message' => '썸네일이 업로드되었습니다.',
        ]);
    }

    public function deleteThumbnail(int $id): JsonResponse
    {
        $hospital = PartnerHospital::findOrFail($id);

        if ($hospital->thumbnail_path) {
            Storage::disk('s3')->delete($hospital->thumbnail_path);
            $hospital->update(['thumbnail_path' => null]);
        }

        return response()->json([
            'success' => true,
            'message' => '썸네일이 삭제되었습니다.',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $hospital = PartnerHospital::findOrFail($id);
        $hospital->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => '병원이 비활성화되었습니다.',
        ]);
    }

    public function activate(int $id): JsonResponse
    {
        $hospital = PartnerHospital::findOrFail($id);
        $hospital->update(['is_active' => true]);

        return response()->json([
            'success' => true,
            'message' => '병원이 활성화되었습니다.',
        ]);
    }

    public function forceDelete(int $id): JsonResponse
    {
        $hospital = PartnerHospital::findOrFail($id);
        $hospital->update(['is_active' => false, 'is_deleted' => false]);
        $hospital->accounts()->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => '병원이 삭제되었습니다.',
        ]);
    }
}
