<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClaimFieldValue;
use App\Models\ClaimFormTemplate;
use App\Models\InsuranceClaim;
use App\Services\ClaimGeneratorService;
use App\Services\FaxService;
use App\Services\PdfGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ClaimController extends Controller
{
    protected ClaimGeneratorService $claimGenerator;
    protected PdfGeneratorService $pdfGenerator;
    protected FaxService $faxService;

    public function __construct(
        ClaimGeneratorService $claimGenerator,
        PdfGeneratorService $pdfGenerator,
        FaxService $faxService
    ) {
        $this->claimGenerator = $claimGenerator;
        $this->pdfGenerator = $pdfGenerator;
        $this->faxService = $faxService;
    }

    /**
     * 내 청구 목록
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = InsuranceClaim::where('user_id', $user->id)
            ->with([
                'claimFormTemplate:id,name,insurance_company_id',
                'claimFormTemplate.insuranceCompany:id,name,code',
            ]);

        // 상태 필터
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $claims = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $claims,
        ]);
    }

    /**
     * 청구서 생성 (작성 완료)
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'claim_form_template_id' => 'required|exists:claim_form_templates,id',
            'fields' => 'required|array',
            'fields.*.template_field_id' => 'required|exists:template_fields,id',
            'fields.*.field_value' => 'nullable|string',
        ]);

        $template = ClaimFormTemplate::with('templateFields')->findOrFail($validated['claim_form_template_id']);

        // 활성화된 템플릿인지 확인
        if (!$template->is_active) {
            return response()->json([
                'success' => false,
                'message' => '비활성화된 양식입니다.',
            ], 400);
        }

        // 필수 필드 검증
        $requiredFields = $template->templateFields->where('is_required', true);
        $inputFieldIds = collect($validated['fields'])->pluck('template_field_id')->toArray();

        foreach ($requiredFields as $requiredField) {
            if (!in_array($requiredField->id, $inputFieldIds)) {
                return response()->json([
                    'success' => false,
                    'message' => "{$requiredField->field_label} 필드는 필수입니다.",
                ], 422);
            }

            $inputField = collect($validated['fields'])->firstWhere('template_field_id', $requiredField->id);
            if (empty($inputField['field_value'])) {
                return response()->json([
                    'success' => false,
                    'message' => "{$requiredField->field_label} 필드는 필수입니다.",
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            // 청구 레코드 생성
            $claim = InsuranceClaim::create([
                'user_id' => $user->id,
                'claim_form_template_id' => $template->id,
                'status' => 'pending',
            ]);

            // 필드 값 저장
            foreach ($validated['fields'] as $fieldData) {
                ClaimFieldValue::create([
                    'insurance_claim_id' => $claim->id,
                    'template_field_id' => $fieldData['template_field_id'],
                    'field_value' => $fieldData['field_value'] ?? '',
                ]);
            }

            // 청구서 이미지 생성
            $imagePath = $this->claimGenerator->generateClaimImage($claim);
            $claim->update(['generated_image_path' => $imagePath]);

            // PDF 생성
            $pdfPath = $this->pdfGenerator->generateClaimPdf($claim);
            $claim->update(['generated_pdf_path' => $pdfPath]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $claim->load([
                    'claimFormTemplate:id,name,insurance_company_id',
                    'claimFormTemplate.insuranceCompany:id,name,code',
                    'fieldValues.templateField:id,field_label,field_type',
                ]),
                'message' => '청구서가 생성되었습니다.',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => '청구서 생성 중 오류가 발생했습니다: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 청구 상세
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $claim = InsuranceClaim::where('user_id', $user->id)
            ->with([
                'claimFormTemplate:id,name,description,insurance_company_id,template_image_path',
                'claimFormTemplate.insuranceCompany:id,name,code,fax_number',
                'fieldValues.templateField:id,field_name,field_label,field_type',
            ])
            ->findOrFail($id);

        // URL 추가
        $claim->generated_image_url = $claim->generated_image_url;
        $claim->generated_pdf_url = $claim->generated_pdf_url;

        return response()->json([
            'success' => true,
            'data' => $claim,
        ]);
    }

    /**
     * 청구서 이미지 다운로드
     */
    public function downloadImage(Request $request, int $id)
    {
        $user = $request->user();

        $claim = InsuranceClaim::where('user_id', $user->id)->findOrFail($id);

        if (!$claim->generated_image_path) {
            return response()->json([
                'success' => false,
                'message' => '생성된 이미지가 없습니다.',
            ], 404);
        }

        $path = Storage::disk('public')->path($claim->generated_image_path);

        if (!file_exists($path)) {
            return response()->json([
                'success' => false,
                'message' => '파일을 찾을 수 없습니다.',
            ], 404);
        }

        $filename = '청구서_' . $claim->id . '_' . date('Ymd') . '.png';

        return response()->download($path, $filename);
    }

    /**
     * 청구서 PDF 다운로드
     */
    public function downloadPdf(Request $request, int $id)
    {
        $user = $request->user();

        $claim = InsuranceClaim::where('user_id', $user->id)->findOrFail($id);

        if (!$claim->generated_pdf_path) {
            return response()->json([
                'success' => false,
                'message' => '생성된 PDF가 없습니다.',
            ], 404);
        }

        $path = Storage::disk('public')->path($claim->generated_pdf_path);

        if (!file_exists($path)) {
            return response()->json([
                'success' => false,
                'message' => '파일을 찾을 수 없습니다.',
            ], 404);
        }

        $filename = '청구서_' . $claim->id . '_' . date('Ymd') . '.pdf';

        return response()->download($path, $filename);
    }

    /**
     * 팩스 발송 (Mock)
     */
    public function sendFax(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $claim = InsuranceClaim::where('user_id', $user->id)->findOrFail($id);

        $validated = $request->validate([
            'fax_number' => 'nullable|string|max:20',
        ]);

        $result = $this->faxService->sendFax($claim, $validated['fax_number'] ?? null);

        if ($result['success']) {
            $claim->update([
                'fax_status' => 'sent',
                'fax_sent_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => $result['message'],
            ]);
        }

        $claim->update([
            'fax_status' => 'failed',
        ]);

        return response()->json([
            'success' => false,
            'message' => $result['message'],
        ], 400);
    }

    /**
     * 관리자: 전체 청구 목록
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $query = InsuranceClaim::with([
            'user:id,name,email,phone',
            'claimFormTemplate:id,name,insurance_company_id',
            'claimFormTemplate.insuranceCompany:id,name,code',
        ]);

        // 검색
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // 상태 필터
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // 보험사 필터
        if ($request->has('insurance_company_id')) {
            $query->whereHas('claimFormTemplate', function ($q) use ($request) {
                $q->where('insurance_company_id', $request->insurance_company_id);
            });
        }

        // 날짜 필터
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $claims = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $claims,
        ]);
    }

    /**
     * 관리자: 청구 상태 변경
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $claim = InsuranceClaim::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:pending,processing,completed,rejected',
            'notes' => 'nullable|string',
        ]);

        $claim->update($validated);

        return response()->json([
            'success' => true,
            'data' => $claim->load([
                'user:id,name,email',
                'claimFormTemplate:id,name,insurance_company_id',
                'claimFormTemplate.insuranceCompany:id,name,code',
            ]),
            'message' => '청구 상태가 변경되었습니다.',
        ]);
    }
}
