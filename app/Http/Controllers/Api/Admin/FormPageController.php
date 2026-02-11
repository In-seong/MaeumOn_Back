<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClaimForm;
use App\Models\FormPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class FormPageController extends Controller
{
    /**
     * 템플릿의 모든 페이지 목록
     */
    public function index(int $templateId): JsonResponse
    {
        $template = ClaimForm::findOrFail($templateId);

        $pages = $template->formPages()
            ->with('formFields')
            ->orderBy('page_number')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $pages,
        ]);
    }

    /**
     * 새 페이지 추가
     */
    public function store(Request $request, int $templateId): JsonResponse
    {
        $template = ClaimForm::findOrFail($templateId);

        $validated = $request->validate([
            'page_number' => 'nullable|integer|min:1',
        ]);

        // 페이지 번호 자동 할당 (기존 최대 + 1)
        if (empty($validated['page_number'])) {
            $maxPageNumber = $template->formPages()->max('page_number') ?? 0;
            $validated['page_number'] = $maxPageNumber + 1;
        }

        // 중복 체크
        $exists = $template->formPages()
            ->where('page_number', $validated['page_number'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => '이미 존재하는 페이지 번호입니다.',
            ], 400);
        }

        $page = $template->formPages()->create($validated);

        return response()->json([
            'success' => true,
            'data' => $page,
            'message' => '페이지가 추가되었습니다.',
        ], 201);
    }

    /**
     * 페이지 상세
     */
    public function show(int $templateId, int $pageId): JsonResponse
    {
        $page = FormPage::where('claim_form_id', $templateId)
            ->with('formFields')
            ->findOrFail($pageId);

        return response()->json([
            'success' => true,
            'data' => $page,
        ]);
    }

    /**
     * 페이지 수정
     */
    public function update(Request $request, int $templateId, int $pageId): JsonResponse
    {
        $page = FormPage::where('claim_form_id', $templateId)
            ->findOrFail($pageId);

        $validated = $request->validate([
            'page_number' => 'sometimes|required|integer|min:1',
        ]);

        if (isset($validated['page_number'])) {
            // 중복 체크 (자신 제외)
            $exists = FormPage::where('claim_form_id', $templateId)
                ->where('page_number', $validated['page_number'])
                ->where('form_page_id', '!=', $pageId)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => '이미 존재하는 페이지 번호입니다.',
                ], 400);
            }
        }

        $page->update($validated);

        return response()->json([
            'success' => true,
            'data' => $page,
            'message' => '페이지가 수정되었습니다.',
        ]);
    }

    /**
     * 페이지 삭제
     */
    public function destroy(int $templateId, int $pageId): JsonResponse
    {
        $page = FormPage::where('claim_form_id', $templateId)
            ->findOrFail($pageId);

        // 이미지 파일 삭제
        if ($page->page_image_path) {
            Storage::disk('s3')->delete($page->page_image_path);
        }

        $page->delete();

        // 페이지 번호 재정렬
        $this->reorderPages($templateId);

        return response()->json([
            'success' => true,
            'message' => '페이지가 삭제되었습니다.',
        ]);
    }

    /**
     * 페이지 이미지 업로드
     */
    public function uploadImage(Request $request, int $templateId, int $pageId): JsonResponse
    {
        $page = FormPage::where('claim_form_id', $templateId)
            ->findOrFail($pageId);

        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:10240', // 최대 10MB
        ]);

        // 기존 이미지 삭제
        if ($page->page_image_path) {
            Storage::disk('s3')->delete($page->page_image_path);
        }

        // 새 이미지 저장
        $file = $request->file('image');
        $filename = 'template_' . $templateId . '_page_' . $page->page_number . '_' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('templates', $filename, 's3');

        // 이미지 크기 가져오기
        $image = Image::read($file->path());
        $width = $image->width();
        $height = $image->height();

        // 페이지 업데이트
        $page->update([
            'page_image_path' => $path,
            'image_width' => $width,
            'image_height' => $height,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'page_image_path' => $path,
                'page_image_url' => $page->fresh()->page_image_url,
                'image_width' => $width,
                'image_height' => $height,
            ],
            'message' => '이미지가 업로드되었습니다.',
        ]);
    }

    /**
     * 페이지 순서 변경
     */
    public function reorder(Request $request, int $templateId): JsonResponse
    {
        $request->validate([
            'pages' => 'required|array',
            'pages.*.form_page_id' => 'required|exists:form_page,form_page_id',
            'pages.*.page_number' => 'required|integer|min:1',
        ]);

        foreach ($request->pages as $pageData) {
            FormPage::where('form_page_id', $pageData['form_page_id'])
                ->where('claim_form_id', $templateId)
                ->update(['page_number' => $pageData['page_number']]);
        }

        return response()->json([
            'success' => true,
            'message' => '페이지 순서가 변경되었습니다.',
        ]);
    }

    /**
     * 페이지 번호 재정렬 (내부 메서드)
     */
    private function reorderPages(int $templateId): void
    {
        $pages = FormPage::where('claim_form_id', $templateId)
            ->orderBy('page_number')
            ->get();

        $newNumber = 1;
        foreach ($pages as $page) {
            $page->update(['page_number' => $newNumber]);
            $newNumber++;
        }
    }
}
