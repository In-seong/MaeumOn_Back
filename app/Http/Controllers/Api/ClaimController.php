<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\BranchFilterable;
use App\Models\ClaimDocument;
use App\Models\ClaimFieldValue;
use App\Models\ClaimForm;
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
    use BranchFilterable;
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
        $customerId = $request->user()->customer->customer_id;

        $query = InsuranceClaim::where('customer_id', $customerId)
            ->with([
                'claimForm:claim_form_id,form_name,company_id',
                'claimForm.insuranceCompany:company_id,company_name,company_code',
            ]);

        // 상태 필터
        if ($request->has('status')) {
            $query->where('claim_status', $request->status);
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
        $account = $request->user();
        $account->load('customer');
        $customerId = $account->customer->customer_id;

        $validated = $request->validate([
            'claim_form_id' => 'required|exists:claim_form,claim_form_id',
            'fields' => 'required|array',
            'fields.*.form_field_id' => 'required|exists:form_field,form_field_id',
            'fields.*.field_value' => 'nullable|string',
        ]);

        $claimForm = ClaimForm::with('formFields')->findOrFail($validated['claim_form_id']);

        // 활성화된 양식인지 확인
        if (!$claimForm->is_active) {
            return response()->json([
                'success' => false,
                'message' => '비활성화된 양식입니다.',
            ], 400);
        }

        // 필수 필드 검증
        $requiredFields = $claimForm->formFields->where('is_required', true);
        $inputFieldIds = collect($validated['fields'])->pluck('form_field_id')->toArray();

        foreach ($requiredFields as $requiredField) {
            if (!in_array($requiredField->form_field_id, $inputFieldIds)) {
                return response()->json([
                    'success' => false,
                    'message' => "{$requiredField->field_label} 필드는 필수입니다.",
                ], 422);
            }

            $inputField = collect($validated['fields'])->firstWhere('form_field_id', $requiredField->form_field_id);
            $fieldValue = $inputField['field_value'] ?? '';
            $isEmpty = match ($requiredField->field_type) {
                'checkbox' => empty($fieldValue) || $fieldValue === '[]',
                'consent' => $fieldValue !== 'agree',
                'signature' => !str_starts_with($fieldValue, 'data:image/'),
                default => empty(trim($fieldValue)),
            };
            if ($isEmpty) {
                return response()->json([
                    'success' => false,
                    'message' => "{$requiredField->field_label} 필드는 필수입니다.",
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            // claim_number 자동 생성
            $claimNumber = 'CLM-' . now()->format('Ymd') . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

            // 청구 레코드 생성
            $claim = InsuranceClaim::create([
                'customer_id' => $customerId,
                'company_id' => $claimForm->company_id,
                'claim_form_id' => $claimForm->claim_form_id,
                'claim_number' => $claimNumber,
                'claim_type' => '직접청구',
                'claim_status' => InsuranceClaim::STATUS_PENDING,
                'claim_date' => now()->toDateString(),
            ]);

            // 필드 값 저장
            foreach ($validated['fields'] as $fieldData) {
                ClaimFieldValue::create([
                    'claim_id' => $claim->claim_id,
                    'form_field_id' => $fieldData['form_field_id'],
                    'field_value' => $fieldData['field_value'] ?? '',
                ]);
            }

            // 이미지 생성 (메모리 내)
            $imageBinaries = $this->claimGenerator->generateClaimImages($claim);

            // PDF 생성 → S3 저장
            $pdfPath = $this->pdfGenerator->generateClaimPdf($claim, $imageBinaries);
            $claim->update(['generated_pdf_path' => $pdfPath]);

            // 페이지별 이미지도 S3에 저장 (WebView PDF 미지원 대응)
            $this->savePageImages($claim, $imageBinaries);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $claim->load([
                    'claimForm:claim_form_id,form_name,company_id',
                    'claimForm.insuranceCompany:company_id,company_name,company_code',
                    'fieldValues.formField:form_field_id,field_label,field_type',
                ]),
                'message' => '청구서가 생성되었습니다.',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            $message = app()->isProduction()
                ? '청구서 생성 중 오류가 발생했습니다.'
                : '청구서 생성 중 오류가 발생했습니다: ' . $e->getMessage();

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 500);
        }
    }

    /**
     * 청구서 수정
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $customerId = $request->user()->customer->customer_id;

        $claim = InsuranceClaim::where('customer_id', $customerId)
            ->where('claim_status', InsuranceClaim::STATUS_PENDING)
            ->findOrFail($id);

        $validated = $request->validate([
            'fields' => 'required|array',
            'fields.*.form_field_id' => 'required|exists:form_field,form_field_id',
            'fields.*.field_value' => 'nullable|string',
        ]);

        // 필수 필드 검증
        $claimForm = $claim->claimForm()->with('formFields')->first();
        $requiredFields = $claimForm->formFields->where('is_required', true);
        $inputFieldIds = collect($validated['fields'])->pluck('form_field_id')->toArray();

        foreach ($requiredFields as $requiredField) {
            if (!in_array($requiredField->form_field_id, $inputFieldIds)) {
                return response()->json([
                    'success' => false,
                    'message' => "{$requiredField->field_label} 필드는 필수입니다.",
                ], 422);
            }

            $inputField = collect($validated['fields'])->firstWhere('form_field_id', $requiredField->form_field_id);
            $fieldValue = $inputField['field_value'] ?? '';
            $isEmpty = match ($requiredField->field_type) {
                'checkbox' => empty($fieldValue) || $fieldValue === '[]',
                'consent' => $fieldValue !== 'agree',
                'signature' => !str_starts_with($fieldValue, 'data:image/'),
                default => empty(trim($fieldValue)),
            };
            if ($isEmpty) {
                return response()->json([
                    'success' => false,
                    'message' => "{$requiredField->field_label} 필드는 필수입니다.",
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            // 기존 필드값 삭제 후 재생성
            $claim->fieldValues()->delete();
            foreach ($validated['fields'] as $fieldData) {
                ClaimFieldValue::create([
                    'claim_id' => $claim->claim_id,
                    'form_field_id' => $fieldData['form_field_id'],
                    'field_value' => $fieldData['field_value'] ?? '',
                ]);
            }

            // 기존 PDF + 페이지 이미지 삭제
            if ($claim->generated_pdf_path) {
                Storage::disk('s3')->delete($claim->generated_pdf_path);
            }
            $this->deletePageImages($claim);

            // PDF 재생성
            $imageBinaries = $this->claimGenerator->generateClaimImages($claim);
            $pdfPath = $this->pdfGenerator->generateClaimPdf($claim, $imageBinaries);
            $claim->update(['generated_pdf_path' => $pdfPath]);

            // 페이지별 이미지도 S3에 저장 (WebView PDF 미지원 대응)
            $this->savePageImages($claim, $imageBinaries);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $claim->load([
                    'claimForm:claim_form_id,form_name,company_id',
                    'claimForm.insuranceCompany:company_id,company_name,company_code',
                    'fieldValues.formField:form_field_id,field_label,field_type',
                ]),
                'message' => '청구서가 수정되었습니다.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            $message = app()->isProduction()
                ? '청구서 수정 중 오류가 발생했습니다.'
                : '청구서 수정 중 오류가 발생했습니다: ' . $e->getMessage();

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 500);
        }
    }

    /**
     * 청구 상세
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $customerId = $request->user()->customer->customer_id;

        $claim = InsuranceClaim::where('customer_id', $customerId)
            ->with([
                'claimForm:claim_form_id,form_name,form_description,company_id',
                'claimForm.insuranceCompany:company_id,company_name,company_code,fax_number',
                'fieldValues.formField:form_field_id,field_name,field_label,field_type',
                'documents',
            ])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $claim,
        ]);
    }

    /**
     * 청구서 PDF 다운로드 (S3 streamDownload)
     */
    public function downloadPdf(Request $request, int $id)
    {
        $customerId = $request->user()->customer->customer_id;

        $claim = InsuranceClaim::where('customer_id', $customerId)->findOrFail($id);

        if (!$claim->generated_pdf_path) {
            return response()->json([
                'success' => false,
                'message' => '생성된 PDF가 없습니다.',
            ], 404);
        }

        if (!Storage::disk('s3')->exists($claim->generated_pdf_path)) {
            return response()->json([
                'success' => false,
                'message' => '파일을 찾을 수 없습니다.',
            ], 404);
        }

        $filename = '청구서_' . $claim->claim_id . '_' . now()->format('Ymd') . '.pdf';

        return response()->streamDownload(function () use ($claim) {
            echo Storage::disk('s3')->get($claim->generated_pdf_path);
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    /**
     * 팩스 발송
     */
    public function sendFax(Request $request, int $id): JsonResponse
    {
        $customerId = $request->user()->customer->customer_id;

        $claim = InsuranceClaim::where('customer_id', $customerId)->findOrFail($id);
        $claim->load('documents');

        if (in_array($claim->fax_status, ['pending', 'sending'])) {
            return response()->json([
                'success' => false,
                'message' => '팩스 발송이 진행 중입니다. 완료 후 재시도해주세요.',
            ], 422);
        }

        $validated = $request->validate([
            'fax_number' => 'nullable|string|max:20',
        ]);

        $faxNumber = $validated['fax_number'] ?? null;

        // 첨부파일이 있으면 병합 PDF로 발송
        if ($claim->documents->isNotEmpty()) {
            $mergedPdf = $this->pdfGenerator->mergeClaimWithAttachments($claim);
            $result = $this->faxService->sendFaxWithContent($claim, $mergedPdf, $faxNumber);
        } else {
            $result = $this->faxService->sendFax($claim, $faxNumber);
        }

        if ($result['success']) {
            $claim->update([
                'fax_status' => 'pending',
                'fax_sent_at' => now(),
                'fax_batch_id' => $result['reference_id'],
                'fax_number_sent' => $result['fax_number'],
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
     * 첨부파일 업로드
     */
    public function uploadDocument(Request $request, int $id): JsonResponse
    {
        $customerId = $request->user()->customer->customer_id;
        $claim = InsuranceClaim::where('customer_id', $customerId)->findOrFail($id);

        // 청구건당 최대 첨부파일 수 제한
        $currentCount = ClaimDocument::where('claim_id', $claim->claim_id)->count();
        if ($currentCount >= 20) {
            return response()->json([
                'success' => false,
                'message' => '첨부파일은 최대 20장까지 가능합니다.',
            ], 422);
        }

        $request->validate([
            'document' => 'required|file|mimes:jpeg,jpg,png,gif,heic,heif,webp,pdf|max:20480',
            'supporting_document_id' => 'nullable|integer',
        ]);

        $file = $request->file('document');

        // supporting_document_id: 명시적 지정 → 보험사 "기타" 조회 → 없으면 자동 생성
        $supportingDocId = $request->input('supporting_document_id');
        if (!$supportingDocId) {
            $supportingDoc = DB::table('supporting_document')
                ->where('company_id', $claim->company_id)
                ->where('document_name', '기타')
                ->first();

            if ($supportingDoc) {
                $supportingDocId = $supportingDoc->supporting_document_id;
            } else {
                $supportingDocId = DB::table('supporting_document')->insertGetId([
                    'company_id' => $claim->company_id,
                    'document_name' => '기타',
                    'document_description' => '기타 첨부파일',
                    'is_required' => 0,
                    'is_active' => 1,
                    'created_at' => now(),
                ]);
            }
        }

        $filename = 'doc_' . $claim->claim_id . '_' . time() . '_' . random_int(100, 999) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('claims/' . $claim->claim_id . '/documents', $filename, 's3');

        $document = ClaimDocument::create([
            'claim_id' => $claim->claim_id,
            'supporting_document_id' => $supportingDocId,
            'document_file_url' => $path,
            'document_file_name' => $file->getClientOriginalName(),
            'document_file_size' => $file->getSize(),
            'upload_status' => 'uploaded',
            'created_by_id' => $customerId,
        ]);

        return response()->json([
            'success' => true,
            'data' => $document,
            'message' => '파일이 업로드되었습니다.',
        ], 201);
    }

    /**
     * 첨부파일 삭제
     */
    public function deleteDocument(Request $request, int $claimId, int $docId): JsonResponse
    {
        $customerId = $request->user()->customer->customer_id;
        $claim = InsuranceClaim::where('customer_id', $customerId)->findOrFail($claimId);

        $document = ClaimDocument::where('claim_id', $claim->claim_id)->findOrFail($docId);

        // S3 파일 삭제
        if ($document->document_file_url) {
            Storage::disk('s3')->delete($document->document_file_url);
        }

        $document->delete();

        return response()->json([
            'success' => true,
            'message' => '파일이 삭제되었습니다.',
        ]);
    }

    /**
     * 관리자: 전체 청구 목록
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $query = InsuranceClaim::with([
            'customer:customer_id,name,phone,email',
            'claimForm:claim_form_id,form_name,company_id',
            'claimForm.insuranceCompany:company_id,company_name,company_code',
        ]);

        $branchId = $this->resolveBranchId($request);
        if ($branchId !== null) {
            $query->whereHas('customer.agent.branches', fn($q) => $q->where('branch.branch_id', $branchId));
        }

        // 검색
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('customer', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // 상태 필터
        if ($request->has('status')) {
            $query->where('claim_status', $request->status);
        }

        // 보험사 필터
        if ($request->has('insurance_company_id')) {
            $query->whereHas('claimForm', function ($q) use ($request) {
                $q->where('company_id', $request->insurance_company_id);
            });
        }

        // 날짜 필터
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // 정렬
        $sortField = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        if (in_array($sortField, ['created_at', 'claim_status', 'approved_amount'])) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        }

        $claims = $query->paginate($request->get('per_page', 15));

        // 목록에서 S3 접근하는 appends 제거 (성능 최적화)
        $claims->getCollection()->each(fn($c) => $c->setAppends([]));

        return response()->json([
            'success' => true,
            'data' => $claims,
        ]);
    }

    /**
     * 관리자: 청구 상세
     */
    public function adminShow(int $id, FaxService $faxService): JsonResponse
    {
        $claim = InsuranceClaim::with([
            'customer:customer_id,name,phone,email',
            'agent:agent_id,name,phone',
            'claimForm:claim_form_id,form_name,company_id',
            'claimForm.insuranceCompany:company_id,company_name,company_code,fax_number',
            'fieldValues.formField:form_field_id,field_name,field_label,field_type,standard_field_code',
            'documents',
        ])->findOrFail($id);

        $data = $claim->toArray();
        if ($claim->fax_result_code) {
            $data['fax_result_message'] = $faxService->getResultMessage($claim->fax_result_code);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * 관리자: 청구 상태 변경
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $claim = InsuranceClaim::findOrFail($id);

        $validStatuses = implode(',', InsuranceClaim::VALID_STATUSES);
        $validated = $request->validate([
            'claim_status' => "required|in:{$validStatuses}",
            'approved_amount' => 'nullable|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if (!$claim->canTransitionTo($validated['claim_status'])) {
            return response()->json([
                'success' => false,
                'message' => "'{$claim->status_label}' 상태에서 '{$validated['claim_status']}' 상태로 변경할 수 없습니다.",
            ], 422);
        }

        // approved 상태로 전환 시 승인일 자동 설정
        if ($validated['claim_status'] === InsuranceClaim::STATUS_APPROVED) {
            $validated['approval_date'] = now()->toDateString();
        }

        // paid 상태로 전환 시 지급일 자동 설정
        if ($validated['claim_status'] === InsuranceClaim::STATUS_PAID) {
            $validated['paid_date'] = now()->toDateString();
            // paid_amount가 없으면 approved_amount를 기본값으로
            if (empty($validated['paid_amount'])) {
                $validated['paid_amount'] = $claim->approved_amount;
            }
        }

        $claim->update($validated);

        return response()->json([
            'success' => true,
            'data' => $claim->load([
                'customer:customer_id,name,email',
                'claimForm:claim_form_id,form_name,company_id',
                'claimForm.insuranceCompany:company_id,company_name,company_code',
            ]),
            'message' => '청구 상태가 변경되었습니다.',
        ]);
    }

    /**
     * 페이지별 이미지를 S3에 저장 (WebView PDF 미지원 대응)
     *
     * @param array<array{page_number: int, binary: string}> $imageBinaries
     */
    private function savePageImages(InsuranceClaim $claim, array $imageBinaries): void
    {
        foreach ($imageBinaries as $imageData) {
            $s3Key = 'claims/' . $claim->claim_id . '/page_' . $imageData['page_number'] . '.jpg';
            Storage::disk('s3')->put($s3Key, $imageData['binary']);
        }
    }

    /**
     * 기존 페이지 이미지 삭제
     */
    private function deletePageImages(InsuranceClaim $claim): void
    {
        $files = Storage::disk('s3')->files('claims/' . $claim->claim_id);
        foreach ($files as $file) {
            if (preg_match('/page_\d+\.jpg$/', $file)) {
                Storage::disk('s3')->delete($file);
            }
        }
    }
}
