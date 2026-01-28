<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClaimFormTemplate;
use App\Models\InsuranceCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class ClaimFormTemplateController extends Controller
{
    /**
     * 공개 API: 활성화된 양식 목록
     */
    public function publicIndex(Request $request): JsonResponse
    {
        $query = ClaimFormTemplate::active()
            ->with('insuranceCompany:id,name,code')
            ->select('id', 'insurance_company_id', 'name', 'description', 'template_image_path', 'image_width', 'image_height');

        // 보험사 필터
        if ($request->has('insurance_company_id')) {
            $query->where('insurance_company_id', $request->insurance_company_id);
        }

        $templates = $query->orderBy('name')->get();

        // 이미지 URL 추가
        $templates->each(function ($template) {
            $template->template_image_url = $template->template_image_url;
        });

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    /**
     * 공개 API: 양식 상세 + 필드 정보
     */
    public function publicShow(int $id): JsonResponse
    {
        $template = ClaimFormTemplate::active()
            ->with([
                'insuranceCompany:id,name,code,fax_number',
                'templatePages.templateFields',
                'templateFields',
            ])
            ->findOrFail($id);

        $template->template_image_url = $template->template_image_url;

        return response()->json([
            'success' => true,
            'data' => $template,
        ]);
    }

    /**
     * 관리자: 양식 목록 (페이지네이션)
     */
    public function index(Request $request): JsonResponse
    {
        $query = ClaimFormTemplate::with('insuranceCompany:id,name,code');

        // 보험사 필터
        if ($request->has('insurance_company_id')) {
            $query->where('insurance_company_id', $request->insurance_company_id);
        }

        // 검색
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // 활성화 필터
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $templates = $query->withCount('templateFields')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        // 이미지 URL 추가
        $templates->getCollection()->each(function ($template) {
            $template->template_image_url = $template->template_image_url;
        });

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    /**
     * 관리자: 양식 등록 (이미지 포함 가능)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'insurance_company_id' => 'required|exists:insurance_companies,id',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:10240',
        ]);

        $template = ClaimFormTemplate::create([
            'insurance_company_id' => $validated['insurance_company_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        // 이미지가 있으면 페이지 생성 + 이미지 저장
        $images = $request->file('images');
        if ($images && is_array($images) && count($images) > 0) {
            $pageNumber = 1;
            foreach ($images as $imageFile) {
                // 파일명 고유성 보장: template_id + page + timestamp + random
                $filename = "template_{$template->id}_page_{$pageNumber}_" . time() . '_' . uniqid() . '.' . $imageFile->getClientOriginalExtension();
                $path = $imageFile->storeAs('templates', $filename, 'public');

                $image = Image::read(Storage::disk('public')->path($path));

                $template->templatePages()->create([
                    'page_number' => $pageNumber,
                    'page_image_path' => $path,
                    'image_width' => $image->width(),
                    'image_height' => $image->height(),
                ]);
                $pageNumber++;
            }
        } else {
            // 이미지 없으면 빈 첫 페이지 생성
            $template->templatePages()->create(['page_number' => 1]);
        }

        return response()->json([
            'success' => true,
            'data' => $template->load(['insuranceCompany:id,name,code', 'templatePages']),
            'message' => '양식이 등록되었습니다.',
        ], 201);
    }

    /**
     * 관리자: 양식 상세
     */
    public function show(int $id): JsonResponse
    {
        $template = ClaimFormTemplate::with([
            'insuranceCompany:id,name,code,fax_number',
            'templatePages.templateFields',
            'templateFields',
        ])->findOrFail($id);

        $template->template_image_url = $template->template_image_url;

        return response()->json([
            'success' => true,
            'data' => $template,
        ]);
    }

    /**
     * 관리자: 양식 수정
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $template = ClaimFormTemplate::findOrFail($id);

        $validated = $request->validate([
            'insurance_company_id' => 'sometimes|required|exists:insurance_companies,id',
            'name' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $template->update($validated);

        return response()->json([
            'success' => true,
            'data' => $template->load('insuranceCompany:id,name,code'),
            'message' => '양식이 수정되었습니다.',
        ]);
    }

    /**
     * 관리자: 양식 삭제
     */
    public function destroy(int $id): JsonResponse
    {
        $template = ClaimFormTemplate::findOrFail($id);

        // 연관된 청구가 있는지 확인
        if ($template->insuranceClaims()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => '이 양식으로 작성된 청구가 있어 삭제할 수 없습니다.',
            ], 400);
        }

        // 이미지 파일 삭제
        if ($template->template_image_path) {
            Storage::disk('public')->delete($template->template_image_path);
        }

        $template->delete();

        return response()->json([
            'success' => true,
            'message' => '양식이 삭제되었습니다.',
        ]);
    }

    /**
     * 관리자: 양식 이미지 업로드
     */
    public function uploadImage(Request $request, int $id): JsonResponse
    {
        $template = ClaimFormTemplate::findOrFail($id);

        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:10240', // 최대 10MB
        ]);

        // 기존 이미지 삭제
        if ($template->template_image_path) {
            Storage::disk('public')->delete($template->template_image_path);
        }

        // 새 이미지 저장
        $file = $request->file('image');
        $filename = 'template_' . $id . '_' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('templates', $filename, 'public');

        // 이미지 크기 가져오기
        $image = Image::read(Storage::disk('public')->path($path));
        $width = $image->width();
        $height = $image->height();

        // 템플릿 업데이트
        $template->update([
            'template_image_path' => $path,
            'image_width' => $width,
            'image_height' => $height,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'template_image_path' => $path,
                'template_image_url' => $template->fresh()->template_image_url,
                'image_width' => $width,
                'image_height' => $height,
            ],
            'message' => '이미지가 업로드되었습니다.',
        ]);
    }
}
