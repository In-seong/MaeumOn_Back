<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClaimForm;
use App\Models\InsuranceCompany;
use App\Services\PdfToImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class ClaimFormController extends Controller
{
    /**
     * 공개 API: 활성화된 양식 목록
     */
    public function publicIndex(Request $request): JsonResponse
    {
        $query = ClaimForm::active()
            ->with(['insuranceCompany:company_id,company_name,company_code', 'formPages'])
            ->select('claim_form_id', 'company_id', 'form_name', 'form_description');

        // 보험사 필터
        if ($request->has('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        $templates = $query->orderBy('form_name')->get();

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
        $template = ClaimForm::active()
            ->with([
                'insuranceCompany:company_id,company_name,company_code,fax_number,contact_phone',
                'formPages.formFields',
                'formFields',
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
        $query = ClaimForm::with('insuranceCompany:company_id,company_name,company_code,fax_number,contact_phone');

        // 보험사 필터
        if ($request->has('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        // 검색
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('form_name', 'like', "%{$search}%")
                  ->orWhere('form_description', 'like', "%{$search}%");
            });
        }

        // 활성화 필터
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $templates = $query->withCount('formFields')
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
     * 관리자: 양식 등록 (이미지/PDF 포함 가능)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:100',
            'fax_number' => 'required|string|max:20',
            'contact_phone' => 'nullable|string|max:20',
            'form_name' => 'required|string|max:100',
            'form_description' => 'nullable|string',
            'is_active' => 'boolean',
            'images' => 'nullable|array',
            'images.*' => 'file|mimes:jpeg,png,jpg,pdf|max:20480',
        ]);

        $company = InsuranceCompany::create([
            'company_name' => $validated['company_name'],
            'fax_number' => $validated['fax_number'],
            'contact_phone' => $validated['contact_phone'] ?? null,
        ]);

        $template = ClaimForm::create([
            'company_id' => $company->company_id,
            'form_name' => $validated['form_name'],
            'form_description' => $validated['form_description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $uploadedFiles = $request->file('images');
        if ($uploadedFiles && is_array($uploadedFiles) && count($uploadedFiles) > 0) {
            // 업로드된 파일들을 처리 가능한 이미지 목록으로 변환
            // PDF는 페이지별 PNG로 분리, 이미지는 그대로 유지
            $imageSources = [];
            $tempDirs = [];
            $pdfService = app(PdfToImageService::class);

            try {
                foreach ($uploadedFiles as $file) {
                    if ($file->getClientOriginalExtension() === 'pdf') {
                        // PDF → 페이지별 PNG 변환
                        $result = $pdfService->convertToImages($file->path());
                        $tempDirs[] = $result['tempDir'];
                        foreach ($result['images'] as $pngPath) {
                            $imageSources[] = ['type' => 'path', 'path' => $pngPath];
                        }
                    } else {
                        // 이미지 파일 그대로
                        $imageSources[] = ['type' => 'uploaded', 'file' => $file];
                    }
                }

                // 모든 이미지를 페이지 순서대로 처리
                $pageNumber = 1;
                foreach ($imageSources as $source) {
                    $filename = "template_{$template->claim_form_id}_page_{$pageNumber}_" . time() . '_' . uniqid() . '.png';

                    if ($source['type'] === 'uploaded') {
                        $ext = $source['file']->getClientOriginalExtension();
                        $filename = "template_{$template->claim_form_id}_page_{$pageNumber}_" . time() . '_' . uniqid() . '.' . $ext;
                        $path = $source['file']->storeAs('templates', $filename, 's3');
                        $image = Image::read($source['file']->path());
                    } else {
                        Storage::disk('s3')->put("templates/{$filename}", file_get_contents($source['path']));
                        $path = "templates/{$filename}";
                        $image = Image::read($source['path']);
                    }

                    $template->formPages()->create([
                        'page_number' => $pageNumber,
                        'page_image_path' => $path,
                        'image_width' => $image->width(),
                        'image_height' => $image->height(),
                    ]);
                    $pageNumber++;
                }
            } finally {
                // 임시 파일 정리
                foreach ($tempDirs as $dir) {
                    $pdfService->cleanup($dir);
                }
            }
        } else {
            // 파일 없으면 빈 첫 페이지 생성
            $template->formPages()->create(['page_number' => 1]);
        }

        return response()->json([
            'success' => true,
            'data' => $template->load(['insuranceCompany:company_id,company_name,company_code', 'formPages']),
            'message' => '양식이 등록되었습니다.',
        ], 201);
    }

    /**
     * 관리자: 양식 상세
     */
    public function show(int $id): JsonResponse
    {
        $template = ClaimForm::with([
            'insuranceCompany:company_id,company_name,company_code,fax_number,contact_phone',
            'formPages.formFields',
            'formFields',
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
        $template = ClaimForm::findOrFail($id);

        $validated = $request->validate([
            'company_name' => 'sometimes|required|string|max:100',
            'fax_number' => 'sometimes|required|string|max:20',
            'contact_phone' => 'nullable|string|max:20',
            'form_name' => 'sometimes|required|string|max:100',
            'form_description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if (isset($validated['company_name']) || isset($validated['fax_number']) || array_key_exists('contact_phone', $validated)) {
            $company = InsuranceCompany::find($template->company_id);
            if ($company) {
                $companyData = [];
                if (isset($validated['company_name'])) $companyData['company_name'] = $validated['company_name'];
                if (isset($validated['fax_number'])) $companyData['fax_number'] = $validated['fax_number'];
                if (array_key_exists('contact_phone', $validated)) $companyData['contact_phone'] = $validated['contact_phone'];
                $company->update($companyData);
            }
        }

        $templateData = array_intersect_key($validated, array_flip(['form_name', 'form_description', 'is_active']));
        if (!empty($templateData)) {
            $template->update($templateData);
        }

        return response()->json([
            'success' => true,
            'data' => $template->load('insuranceCompany:company_id,company_name,company_code,fax_number,contact_phone'),
            'message' => '양식이 수정되었습니다.',
        ]);
    }

    /**
     * 관리자: 양식 삭제
     */
    public function destroy(int $id): JsonResponse
    {
        $template = ClaimForm::findOrFail($id);

        // 연관된 청구가 있는지 확인
        if ($template->insuranceClaims()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => '이 양식으로 작성된 청구가 있어 삭제할 수 없습니다.',
            ], 400);
        }

        // 이미지 파일 삭제
        if ($template->template_image_path) {
            Storage::disk('s3')->delete($template->template_image_path);
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
        $template = ClaimForm::findOrFail($id);

        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:10240', // 최대 10MB
        ]);

        // 기존 이미지 삭제
        if ($template->template_image_path) {
            Storage::disk('s3')->delete($template->template_image_path);
        }

        // 새 이미지 저장
        $file = $request->file('image');
        $filename = 'template_' . $id . '_' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('templates', $filename, 's3');

        // 이미지 크기 가져오기
        $image = Image::read($file->path());
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
