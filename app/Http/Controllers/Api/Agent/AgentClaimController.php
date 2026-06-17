<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\ClaimDocument;
use App\Models\ClaimFieldValue;
use App\Models\ClaimForm;
use App\Models\Customer;
use App\Models\InsuranceClaim;
use App\Services\ClaimGeneratorService;
use App\Services\FaxService;
use App\Services\PdfGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AgentClaimController extends Controller
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
     * 설계사 담당 고객의 보험청구 목록
     */
    public function index(Request $request): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $query = InsuranceClaim::where('agent_id', $agentId)
            ->with([
                'customer:customer_id,name,phone',
                'claimForm:claim_form_id,form_name,company_id',
                'claimForm.insuranceCompany:company_id,company_name',
            ]);

        // 상태 필터
        if ($request->has('claim_status')) {
            $query->where('claim_status', $request->claim_status);
        }

        // 검색 (고객명, 청구번호, 양식명)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('customer', function ($cq) use ($search) {
                    $cq->where('name', 'like', "%{$search}%");
                })
                ->orWhere('claim_number', 'like', "%{$search}%")
                ->orWhereHas('claimForm', function ($fq) use ($search) {
                    $fq->where('form_name', 'like', "%{$search}%");
                });
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
            ->paginate(min(max((int) $request->get('per_page', 15), 1), 100));

        // 목록에서 S3 접근하는 appends 제거 (성능 최적화)
        $claims->getCollection()->each(fn($c) => $c->setAppends([]));

        // 상태별 전체 건수 (필터/검색 무관, 해당 설계사 전체)
        $statusCounts = InsuranceClaim::where('agent_id', $agentId)
            ->selectRaw("
                COUNT(*) as total,
                SUM(claim_status = 'draft') as draft,
                SUM(claim_status = 'pending') as pending,
                SUM(claim_status = 'processing') as processing,
                SUM(claim_status = 'approved') as approved,
                SUM(claim_status = 'rejected') as rejected,
                SUM(claim_status = 'paid') as paid
            ")
            ->first();

        return response()->json([
            'success' => true,
            'data' => $claims,
            'status_counts' => [
                'all' => (int) $statusCounts->total,
                'draft' => (int) $statusCounts->draft,
                'pending' => (int) $statusCounts->pending,
                'processing' => (int) $statusCounts->processing,
                'approved' => (int) $statusCounts->approved,
                'rejected' => (int) $statusCounts->rejected,
                'paid' => (int) $statusCounts->paid,
            ],
        ]);
    }

    /**
     * 보험청구 상세
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $claim = InsuranceClaim::where('agent_id', $agentId)
            ->with([
                'customer:customer_id,name,phone',
                'claimForm:claim_form_id,form_name,company_id',
                'claimForm.insuranceCompany:company_id,company_name',
                'fieldValues.formField:form_field_id,field_name,field_label,field_type',
                'documents',
            ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $claim,
        ]);
    }

    /**
     * 설계사 대리 청구서 생성
     */
    public function store(Request $request): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $validated = $request->validate([
            'customer_id' => 'nullable|string',
            'claim_form_id' => 'required|integer|exists:claim_form,claim_form_id',
            'fields' => 'required|array',
            'fields.*.form_field_id' => 'required|integer|exists:form_field,form_field_id',
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

        // 고객 처리: 기존 고객 선택 또는 폼 필드에서 자동 생성
        $customer = null;
        if (!empty($validated['customer_id'])) {
            // 기존 고객 선택
            $customer = Customer::where('agent_id', $agentId)
                ->where('customer_id', $validated['customer_id'])
                ->firstOrFail();
        } else {
            // 바로 청구: 폼 필드에서 고객 정보 추출 → 자동 생성
            $customer = $this->createCustomerFromFields(
                $agentId,
                $claimForm->formFields,
                collect($validated['fields'])
            );
        }

        DB::beginTransaction();
        try {
            // claim_number 자동 생성
            $claimNumber = 'CLM-' . now()->format('Ymd') . '-' . substr((string) time(), -4) . str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);

            // 청구 레코드 생성 (agent_id 포함, claim_type '대리청구')
            $claim = InsuranceClaim::create([
                'customer_id' => $customer->customer_id,
                'company_id' => $claimForm->company_id,
                'agent_id' => $agentId,
                'claim_form_id' => $claimForm->claim_form_id,
                'claim_number' => $claimNumber,
                'claim_type' => '대리청구',
                'claim_status' => InsuranceClaim::STATUS_PENDING,
                'claim_date' => now()->toDateString(),
                'created_by_id' => $agentId,
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

            // PDF 생성 -> S3 저장
            $pdfPath = $this->pdfGenerator->generateClaimPdf($claim, $imageBinaries);
            $claim->update(['generated_pdf_path' => $pdfPath]);

            // 페이지별 이미지도 S3에 저장 (WebView PDF 미지원 대응)
            $this->savePageImages($claim, $imageBinaries);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $claim->load([
                    'customer:customer_id,name,phone',
                    'claimForm:claim_form_id,form_name,company_id',
                    'claimForm.insuranceCompany:company_id,company_name,company_code',
                    'fieldValues.formField:form_field_id,field_label,field_type',
                ]),
                'message' => '대리 청구서가 생성되었습니다.',
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
     * 설계사 대리 청구서 수정
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $claim = InsuranceClaim::where('agent_id', $agentId)
            ->where('claim_status', InsuranceClaim::STATUS_PENDING)
            ->findOrFail($id);

        $validated = $request->validate([
            'fields' => 'required|array',
            'fields.*.form_field_id' => 'required|integer|exists:form_field,form_field_id',
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
            $claim->update([
                'generated_pdf_path' => $pdfPath,
                'updated_by_id' => $agentId,
            ]);

            // 페이지별 이미지도 S3에 저장 (WebView PDF 미지원 대응)
            $this->savePageImages($claim, $imageBinaries);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $claim->load([
                    'customer:customer_id,name,phone',
                    'claimForm:claim_form_id,form_name,company_id',
                    'claimForm.insuranceCompany:company_id,company_name,company_code',
                    'fieldValues.formField:form_field_id,field_label,field_type',
                ]),
                'message' => '대리 청구서가 수정되었습니다.',
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
     * 설계사 대리 팩스 발송
     */
    public function sendFax(Request $request, int $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $claim = InsuranceClaim::where('agent_id', $agentId)->findOrFail($id);
        $claim->load('documents');

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
     * 팩스 발송 상태 새로고침 (FC 테이블 즉시 조회)
     */
    public function refreshFaxStatus(Request $request, int $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;
        $claim = InsuranceClaim::where('agent_id', $agentId)->findOrFail($id);

        if (!$claim->fax_batch_id) {
            return response()->json([
                'success' => false,
                'message' => '팩스 발송 기록이 없습니다.',
            ], 400);
        }

        $batchId = (int) $claim->fax_batch_id;
        $msg = \App\Models\FcMsgTran::where('tr_batchid', $batchId)
            ->where('tr_serialno', 1)
            ->first();

        if (!$msg) {
            return response()->json([
                'success' => true,
                'data' => ['fax_status' => $claim->fax_status, 'result_message' => '발송 정보 조회 중'],
            ]);
        }

        $sendStat = trim($msg->tr_sendstat);
        $resultStat = trim($msg->tr_rsltstat ?? '');
        $updated = false;

        if ($sendStat === '1' && $claim->fax_status === 'pending') {
            $claim->update(['fax_status' => 'sending']);
            $updated = true;
        } elseif ($sendStat === '2') {
            if ($resultStat === '0') {
                $claim->update(['fax_status' => 'sent', 'fax_result_code' => $resultStat]);
                $updated = true;
            } elseif ($resultStat !== '-' && $resultStat !== '') {
                $claim->update(['fax_status' => 'failed', 'fax_result_code' => $resultStat]);
                $updated = true;
            }
        }

        $claim->refresh();

        return response()->json([
            'success' => true,
            'data' => [
                'fax_status' => $claim->fax_status,
                'result_message' => $this->faxService->getResultMessage($resultStat ?: '-'),
                'send_stat' => $sendStat,
                'result_stat' => $resultStat,
                'updated' => $updated,
            ],
        ]);
    }

    /**
     * 설계사 대리 첨부파일 업로드
     */
    public function uploadDocument(Request $request, int $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $claim = InsuranceClaim::where('agent_id', $agentId)->findOrFail($id);

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

        // supporting_document_id: 명시적 지정 -> 보험사 "기타" 조회 -> 없으면 자동 생성
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
            'created_by_id' => $agentId,
        ]);

        return response()->json([
            'success' => true,
            'data' => $document,
            'message' => '파일이 업로드되었습니다.',
        ], 201);
    }

    /**
     * 설계사 대리 첨부파일 삭제
     */
    public function deleteDocument(Request $request, int $claimId, int $docId): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $claim = InsuranceClaim::where('agent_id', $agentId)->findOrFail($claimId);

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
     * 설계사 대리 청구서 PDF 다운로드
     */
    public function downloadPdf(Request $request, int $id)
    {
        $agentId = $request->user()->agent->agent_id;

        $claim = InsuranceClaim::where('agent_id', $agentId)->findOrFail($id);

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
     * 수익자 정보 변경 (pending/processing 상태에서 가능)
     */
    public function updateBeneficiary(Request $request, int $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $claim = InsuranceClaim::where('agent_id', $agentId)
            ->whereIn('claim_status', [
                InsuranceClaim::STATUS_PENDING,
                InsuranceClaim::STATUS_PROCESSING,
            ])
            ->findOrFail($id);

        $validated = $request->validate([
            'fields' => 'required|array|min:1',
            'fields.*.form_field_id' => 'required|integer|exists:form_field,form_field_id',
            'fields.*.field_value' => 'nullable|string',
        ]);

        // 수익자 관련 standard_field_code만 허용
        $beneficiaryCodes = ['BENEFICIARY_NAME', 'BENEFICIARY_RRN_FRONT', 'BENEFICIARY_RRN_BACK', 'BENEFICIARY_PHONE', 'BENEFICIARY_RELATION'];
        $allowedFieldIds = DB::table('form_field')
            ->where('claim_form_id', $claim->claim_form_id)
            ->whereIn('standard_field_code', $beneficiaryCodes)
            ->pluck('form_field_id')
            ->toArray();

        foreach ($validated['fields'] as $fieldData) {
            if (!in_array($fieldData['form_field_id'], $allowedFieldIds)) {
                return response()->json([
                    'success' => false,
                    'message' => '수익자 관련 필드만 변경할 수 있습니다.',
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            foreach ($validated['fields'] as $fieldData) {
                ClaimFieldValue::updateOrCreate(
                    [
                        'claim_id' => $claim->claim_id,
                        'form_field_id' => $fieldData['form_field_id'],
                    ],
                    [
                        'field_value' => $fieldData['field_value'] ?? '',
                    ]
                );
            }

            // PDF 재생성
            if ($claim->generated_pdf_path) {
                Storage::disk('s3')->delete($claim->generated_pdf_path);
            }
            $this->deletePageImages($claim);

            $imageBinaries = $this->claimGenerator->generateClaimImages($claim);
            $pdfPath = $this->pdfGenerator->generateClaimPdf($claim, $imageBinaries);
            $claim->update([
                'generated_pdf_path' => $pdfPath,
                'updated_by_id' => $agentId,
            ]);
            $this->savePageImages($claim, $imageBinaries);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $claim->load([
                    'customer:customer_id,name,phone',
                    'fieldValues.formField:form_field_id,field_label,field_type',
                ]),
                'message' => '수익자 정보가 변경되었습니다.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => '수익자 정보 변경 중 오류가 발생했습니다.',
            ], 500);
        }
    }

    /**
     * 임시저장 생성
     */
    public function saveDraft(Request $request): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $validated = $request->validate([
            'customer_id' => 'nullable|string',
            'claim_form_id' => 'required|integer|exists:claim_form,claim_form_id',
            'fields' => 'required|array',
            'fields.*.form_field_id' => 'required|integer|exists:form_field,form_field_id',
            'fields.*.field_value' => 'nullable|string',
        ]);

        $claimForm = ClaimForm::findOrFail($validated['claim_form_id']);

        DB::beginTransaction();
        try {
            $claimNumber = 'CLM-' . now()->format('Ymd') . '-' . substr((string) time(), -4) . str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);

            $claim = InsuranceClaim::create([
                'customer_id' => $validated['customer_id'] ?? null,
                'company_id' => $claimForm->company_id,
                'agent_id' => $agentId,
                'claim_form_id' => $claimForm->claim_form_id,
                'claim_number' => $claimNumber,
                'claim_type' => '대리청구',
                'claim_status' => InsuranceClaim::STATUS_DRAFT,
                'claim_date' => now()->toDateString(),
                'created_by_id' => $agentId,
            ]);

            foreach ($validated['fields'] as $fieldData) {
                ClaimFieldValue::create([
                    'claim_id' => $claim->claim_id,
                    'form_field_id' => $fieldData['form_field_id'],
                    'field_value' => $fieldData['field_value'] ?? '',
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $claim->load([
                    'customer:customer_id,name,phone',
                    'claimForm:claim_form_id,form_name,company_id',
                    'claimForm.insuranceCompany:company_id,company_name',
                    'fieldValues.formField:form_field_id,field_label,field_type',
                ]),
                'message' => '임시저장되었습니다.',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            $message = app()->isProduction()
                ? '임시저장 중 오류가 발생했습니다.'
                : '임시저장 중 오류가 발생했습니다: ' . $e->getMessage();

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 500);
        }
    }

    /**
     * 임시저장 갱신
     */
    public function updateDraft(Request $request, int $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $claim = InsuranceClaim::where('agent_id', $agentId)
            ->where('claim_status', InsuranceClaim::STATUS_DRAFT)
            ->findOrFail($id);

        $validated = $request->validate([
            'fields' => 'required|array',
            'fields.*.form_field_id' => 'required|integer|exists:form_field,form_field_id',
            'fields.*.field_value' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $claim->fieldValues()->delete();
            foreach ($validated['fields'] as $fieldData) {
                ClaimFieldValue::create([
                    'claim_id' => $claim->claim_id,
                    'form_field_id' => $fieldData['form_field_id'],
                    'field_value' => $fieldData['field_value'] ?? '',
                ]);
            }

            $claim->update(['updated_by_id' => $agentId]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $claim->load([
                    'customer:customer_id,name,phone',
                    'claimForm:claim_form_id,form_name,company_id',
                    'claimForm.insuranceCompany:company_id,company_name',
                    'fieldValues.formField:form_field_id,field_label,field_type',
                ]),
                'message' => '임시저장이 갱신되었습니다.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            $message = app()->isProduction()
                ? '임시저장 갱신 중 오류가 발생했습니다.'
                : '임시저장 갱신 중 오류가 발생했습니다: ' . $e->getMessage();

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 500);
        }
    }

    /**
     * 임시저장 → 정식 제출
     */
    public function submitDraft(Request $request, int $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $claim = InsuranceClaim::where('agent_id', $agentId)
            ->where('claim_status', InsuranceClaim::STATUS_DRAFT)
            ->findOrFail($id);

        $request->validate([
            'customer_id' => 'nullable|string',
        ]);

        $claimForm = ClaimForm::with('formFields')->findOrFail($claim->claim_form_id);

        // 필수 필드 검증 (기존 store()와 동일)
        $requiredFields = $claimForm->formFields->where('is_required', true);
        $fieldValues = $claim->fieldValues()->get();
        $inputFieldIds = $fieldValues->pluck('form_field_id')->toArray();

        foreach ($requiredFields as $requiredField) {
            if (!in_array($requiredField->form_field_id, $inputFieldIds)) {
                return response()->json([
                    'success' => false,
                    'message' => "{$requiredField->field_label} 필드는 필수입니다.",
                ], 422);
            }

            $inputField = $fieldValues->firstWhere('form_field_id', $requiredField->form_field_id);
            $fieldValue = $inputField->field_value ?? '';
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

        // 고객 처리
        $customerId = $request->input('customer_id') ?? $claim->customer_id;
        $customer = null;
        if (!empty($customerId)) {
            $customer = Customer::where('agent_id', $agentId)
                ->where('customer_id', $customerId)
                ->firstOrFail();
        } else {
            $customer = $this->createCustomerFromFields(
                $agentId,
                $claimForm->formFields,
                $fieldValues->map(fn($fv) => [
                    'form_field_id' => $fv->form_field_id,
                    'field_value' => $fv->field_value,
                ])
            );
        }

        DB::beginTransaction();
        try {
            $claim->update([
                'customer_id' => $customer->customer_id,
                'claim_status' => InsuranceClaim::STATUS_PENDING,
                'claim_date' => now()->toDateString(),
                'updated_by_id' => $agentId,
            ]);

            // 이미지 + PDF 생성
            $imageBinaries = $this->claimGenerator->generateClaimImages($claim);
            $pdfPath = $this->pdfGenerator->generateClaimPdf($claim, $imageBinaries);
            $claim->update(['generated_pdf_path' => $pdfPath]);
            $this->savePageImages($claim, $imageBinaries);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $claim->load([
                    'customer:customer_id,name,phone',
                    'claimForm:claim_form_id,form_name,company_id',
                    'claimForm.insuranceCompany:company_id,company_name,company_code',
                    'fieldValues.formField:form_field_id,field_label,field_type',
                ]),
                'message' => '청구서가 제출되었습니다.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            $message = app()->isProduction()
                ? '청구서 제출 중 오류가 발생했습니다.'
                : '청구서 제출 중 오류가 발생했습니다: ' . $e->getMessage();

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 500);
        }
    }

    /**
     * 임시저장 삭제
     */
    public function deleteDraft(Request $request, int $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $claim = InsuranceClaim::where('agent_id', $agentId)
            ->where('claim_status', InsuranceClaim::STATUS_DRAFT)
            ->findOrFail($id);

        DB::beginTransaction();
        try {
            $claim->fieldValues()->delete();
            $claim->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '임시저장이 삭제되었습니다.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => '삭제 중 오류가 발생했습니다.',
            ], 500);
        }
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

    /**
     * 바로 청구: 폼 필드에서 고객 정보를 추출하여 고객 자동 생성
     *
     * Step 3(계약자) 필드에서 이름, 전화번호, 주민번호 등을 추출하고
     * 해당 정보로 새 고객을 등록한 뒤 반환합니다.
     */
    private function createCustomerFromFields(string $agentId, $formFields, $inputFields): Customer
    {
        $customerData = [
            'name' => null,
            'phone' => null,
            'resident_number' => null,
            'email' => null,
        ];

        // 폼 필드 정의에서 field_name/field_type/field_label 기반으로 고객 정보 추출
        foreach ($formFields as $formField) {
            $inputField = $inputFields->firstWhere('form_field_id', $formField->form_field_id);
            if (!$inputField || empty($inputField['field_value'])) {
                continue;
            }

            $value = trim($inputField['field_value']);
            $fieldName = strtolower($formField->field_name);
            $fieldLabel = strtolower($formField->field_label ?? '');
            $fieldType = $formField->field_type;

            // Step 3(계약자) 필드만 대상 — wizard_step이 3이거나 field_name에 contractor 포함이거나
            // 또는 field_options에 wizard_step=3이 지정된 경우
            $wizardStep = $formField->field_options['wizard_step'] ?? null;
            $isStep3 = $wizardStep == 3
                || str_starts_with($fieldName, 'contractor_')
                || (!$wizardStep && !str_starts_with($fieldName, 'insured_')
                    && !str_starts_with($fieldName, 'beneficiary_')
                    && !str_starts_with($fieldName, 'account_')
                    && !str_starts_with($fieldName, 'bank_'));

            if (!$isStep3) {
                continue;
            }

            // 이름 매칭
            if (!$customerData['name'] && (
                str_contains($fieldName, 'name')
                || str_contains($fieldLabel, '이름')
                || str_contains($fieldLabel, '성명')
            )) {
                $customerData['name'] = $value;
            }

            // 전화번호 매칭
            if (!$customerData['phone'] && (
                $fieldType === 'phone'
                || str_contains($fieldName, 'phone')
                || str_contains($fieldName, 'tel')
                || str_contains($fieldLabel, '휴대')
                || str_contains($fieldLabel, '전화')
                || str_contains($fieldLabel, '연락')
            )) {
                $customerData['phone'] = preg_replace('/\D/', '', $value);
            }

            // 주민번호 매칭: 생년월일(앞6) + 뒷자리(뒤7)를 합침
            if ($fieldType === 'resident_number'
                || str_contains($fieldName, 'jumin')
                || str_contains($fieldName, 'resident')
            ) {
                // 뒷자리 (7자리)
                $digits = preg_replace('/\D/', '', $value);
                if (strlen($digits) === 7) {
                    $customerData['resident_number'] = ($customerData['resident_number'] ?? '') . $digits;
                } elseif (strlen($digits) === 13) {
                    $customerData['resident_number'] = $digits;
                } elseif (strlen($digits) <= 7 && !empty($customerData['resident_number'])) {
                    // 이미 앞자리가 있으면 뒤에 붙임
                    $customerData['resident_number'] .= $digits;
                } else {
                    $customerData['resident_number'] = $digits;
                }
            }

            // 생년월일 → 주민번호 앞 6자리
            if (str_contains($fieldName, 'birth')
                || str_contains($fieldLabel, '생년')
                || str_contains($fieldLabel, '생일')
            ) {
                $digits = preg_replace('/\D/', '', $value);
                if (strlen($digits) === 6) {
                    // 앞 6자리 + 기존 뒷자리
                    $back = $customerData['resident_number'] ?? '';
                    $customerData['resident_number'] = $digits . $back;
                }
            }

            // 이메일 매칭
            if (!$customerData['email'] && (
                str_contains($fieldName, 'email')
                || str_contains($fieldName, 'mail')
                || str_contains($fieldLabel, '이메일')
            )) {
                $customerData['email'] = $value;
            }
        }

        // 이름은 필수 — 없으면 '미입력'
        if (empty($customerData['name'])) {
            $customerData['name'] = '미입력';
        }

        // 주민번호 13자리 초과 시 앞 13자리만
        if (!empty($customerData['resident_number'])) {
            $customerData['resident_number'] = substr(
                preg_replace('/\D/', '', $customerData['resident_number']),
                0,
                13
            );
        }

        // Customer ID 생성 (C + 7자리 순번)
        $lastCustomer = Customer::where('customer_id', 'like', 'C%')
            ->orderByRaw('CAST(SUBSTRING(customer_id, 2) AS UNSIGNED) DESC')
            ->first();
        $nextNum = $lastCustomer
            ? (int) substr($lastCustomer->customer_id, 1) + 1
            : 1;
        $customerId = 'C' . str_pad((string) $nextNum, 7, '0', STR_PAD_LEFT);

        return Customer::create(array_filter([
            'customer_id' => $customerId,
            'agent_id' => $agentId,
            'name' => $customerData['name'],
            'phone' => $customerData['phone'],
            'resident_number' => $customerData['resident_number'],
            'email' => $customerData['email'],
            'is_active' => true,
        ], fn($v) => $v !== null));
    }
}
