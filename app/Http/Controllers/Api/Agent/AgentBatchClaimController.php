<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\BatchClaim;
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

class AgentBatchClaimController extends Controller
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
     * 배치 목록 조회
     */
    public function index(Request $request): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $query = BatchClaim::where('agent_id', $agentId)
            ->with([
                'customer:customer_id,name,phone',
                'claims:claim_id,batch_claim_id,claim_form_id,claim_status,company_id',
                'claims.insuranceCompany:company_id,company_name',
            ]);

        if ($request->has('batch_status')) {
            $query->where('batch_status', $request->batch_status);
        }

        $batches = $query->orderBy('created_at', 'desc')
            ->paginate(min(max((int) $request->get('per_page', 15), 1), 100));

        return response()->json([
            'success' => true,
            'data' => $batches,
        ]);
    }

    /**
     * 배치 상세 조회
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $batch = BatchClaim::where('agent_id', $agentId)
            ->with([
                'customer:customer_id,name,phone,resident_number,email,address',
                'claims' => function ($q) {
                    $q->with([
                        'claimForm:claim_form_id,form_name,company_id',
                        'claimForm.insuranceCompany:company_id,company_name,fax_number,contact_phone',
                        'fieldValues.formField:form_field_id,field_name,field_label,field_type,standard_field_code',
                        'documents',
                    ]);
                },
            ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $batch,
        ]);
    }

    /**
     * 다중 청구 일괄 제출
     */
    public function store(Request $request): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $validated = $request->validate([
            'customer_id' => 'required|string|exists:customer,customer_id',
            'claims' => 'required|array|min:1|max:5',
            'claims.*.claim_form_id' => 'required|integer|exists:claim_form,claim_form_id',
            'claims.*.fields' => 'required|array',
            'claims.*.fields.*.form_field_id' => 'required|integer|exists:form_field,form_field_id',
            'claims.*.fields.*.field_value' => 'nullable|string',
            'claims.*.document_ids' => 'nullable|array',
            'claims.*.document_ids.*' => 'integer',
            'common_document_ids' => 'nullable|array',
            'common_document_ids.*' => 'integer',
        ]);

        // 고객 검증
        $customer = Customer::where('agent_id', $agentId)
            ->where('customer_id', $validated['customer_id'])
            ->firstOrFail();

        // 보험사 중복 체크
        $companyIds = [];
        $claimForms = [];
        foreach ($validated['claims'] as $claimData) {
            $claimForm = ClaimForm::with('formFields')->findOrFail($claimData['claim_form_id']);
            if (!$claimForm->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => "비활성화된 양식입니다: {$claimForm->form_name}",
                ], 400);
            }
            if (in_array($claimForm->company_id, $companyIds)) {
                return response()->json([
                    'success' => false,
                    'message' => '같은 보험사에 대해 중복 청구할 수 없습니다.',
                ], 422);
            }
            $companyIds[] = $claimForm->company_id;
            $claimForms[] = $claimForm;
        }

        // 각 양식의 필수 필드 검증
        foreach ($validated['claims'] as $idx => $claimData) {
            $claimForm = $claimForms[$idx];
            $error = $this->validateRequiredFields($claimForm, $claimData['fields']);
            if ($error) {
                $companyName = $claimForm->insuranceCompany->company_name ?? '알 수 없음';
                return response()->json([
                    'success' => false,
                    'message' => "[{$companyName}] {$error}",
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            // 배치 생성
            $batch = BatchClaim::create([
                'customer_id' => $customer->customer_id,
                'agent_id' => $agentId,
                'batch_status' => BatchClaim::STATUS_PENDING,
                'total_count' => count($validated['claims']),
                'completed_count' => 0,
            ]);

            $createdClaims = [];

            foreach ($validated['claims'] as $idx => $claimData) {
                $claimForm = $claimForms[$idx];

                $claimNumber = 'CLM-' . now()->format('Ymd') . '-' . substr((string) time(), -4) . str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);

                $claim = InsuranceClaim::create([
                    'customer_id' => $customer->customer_id,
                    'batch_claim_id' => $batch->batch_claim_id,
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
                foreach ($claimData['fields'] as $fieldData) {
                    ClaimFieldValue::create([
                        'claim_id' => $claim->claim_id,
                        'form_field_id' => $fieldData['form_field_id'],
                        'field_value' => $fieldData['field_value'] ?? '',
                    ]);
                }

                // 이미지 + PDF 생성
                $imageBinaries = $this->claimGenerator->generateClaimImages($claim);
                $pdfPath = $this->pdfGenerator->generateClaimPdf($claim, $imageBinaries);
                $claim->update(['generated_pdf_path' => $pdfPath]);
                $this->savePageImages($claim, $imageBinaries);

                // 공통 서류 복제 (동일 S3 URL, claim_document 레코드만 생성)
                if (!empty($validated['common_document_ids'])) {
                    $this->attachDocumentsToClaim($claim, $validated['common_document_ids'], $agentId);
                }

                // 개별 서류 복제
                if (!empty($claimData['document_ids'])) {
                    $this->attachDocumentsToClaim($claim, $claimData['document_ids'], $agentId);
                }

                $createdClaims[] = $claim;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $batch->load([
                    'customer:customer_id,name,phone',
                    'claims' => function ($q) {
                        $q->with([
                            'claimForm:claim_form_id,form_name,company_id',
                            'claimForm.insuranceCompany:company_id,company_name',
                            'fieldValues.formField:form_field_id,field_label,field_type',
                            'documents',
                        ]);
                    },
                ]),
                'message' => count($createdClaims) . '건의 청구서가 생성되었습니다.',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            $message = app()->isProduction()
                ? '다중 청구서 생성 중 오류가 발생했습니다.'
                : '다중 청구서 생성 중 오류가 발생했습니다: ' . $e->getMessage();

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 500);
        }
    }

    /**
     * 배치 임시저장
     */
    public function saveDraft(Request $request): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $validated = $request->validate([
            'customer_id' => 'nullable|string',
            'claims' => 'required|array|min:1|max:5',
            'claims.*.claim_form_id' => 'required|integer|exists:claim_form,claim_form_id',
            'claims.*.fields' => 'required|array',
            'claims.*.fields.*.form_field_id' => 'required|integer|exists:form_field,form_field_id',
            'claims.*.fields.*.field_value' => 'nullable|string',
        ]);

        // 고객 검증 (있으면)
        if (!empty($validated['customer_id'])) {
            Customer::where('agent_id', $agentId)
                ->where('customer_id', $validated['customer_id'])
                ->firstOrFail();
        }

        DB::beginTransaction();
        try {
            $batch = BatchClaim::create([
                'customer_id' => $validated['customer_id'] ?? null,
                'agent_id' => $agentId,
                'batch_status' => BatchClaim::STATUS_DRAFT,
                'total_count' => count($validated['claims']),
                'completed_count' => 0,
            ]);

            foreach ($validated['claims'] as $claimData) {
                $claimForm = ClaimForm::findOrFail($claimData['claim_form_id']);

                $claimNumber = 'CLM-' . now()->format('Ymd') . '-' . substr((string) time(), -4) . str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);

                $claim = InsuranceClaim::create([
                    'customer_id' => $validated['customer_id'] ?? null,
                    'batch_claim_id' => $batch->batch_claim_id,
                    'company_id' => $claimForm->company_id,
                    'agent_id' => $agentId,
                    'claim_form_id' => $claimForm->claim_form_id,
                    'claim_number' => $claimNumber,
                    'claim_type' => '대리청구',
                    'claim_status' => InsuranceClaim::STATUS_DRAFT,
                    'claim_date' => now()->toDateString(),
                    'created_by_id' => $agentId,
                ]);

                foreach ($claimData['fields'] as $fieldData) {
                    ClaimFieldValue::create([
                        'claim_id' => $claim->claim_id,
                        'form_field_id' => $fieldData['form_field_id'],
                        'field_value' => $fieldData['field_value'] ?? '',
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $batch->load([
                    'customer:customer_id,name,phone',
                    'claims' => function ($q) {
                        $q->with([
                            'claimForm:claim_form_id,form_name,company_id',
                            'claimForm.insuranceCompany:company_id,company_name',
                            'fieldValues.formField:form_field_id,field_label,field_type',
                        ]);
                    },
                ]),
                'message' => '배치 임시저장되었습니다.',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            $message = app()->isProduction()
                ? '배치 임시저장 중 오류가 발생했습니다.'
                : '배치 임시저장 중 오류가 발생했습니다: ' . $e->getMessage();

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 500);
        }
    }

    /**
     * 배치 임시저장 수정
     */
    public function updateDraft(Request $request, int $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $batch = BatchClaim::where('agent_id', $agentId)
            ->where('batch_status', BatchClaim::STATUS_DRAFT)
            ->findOrFail($id);

        $validated = $request->validate([
            'customer_id' => 'nullable|string',
            'claims' => 'required|array|min:1|max:5',
            'claims.*.claim_form_id' => 'required|integer|exists:claim_form,claim_form_id',
            'claims.*.fields' => 'required|array',
            'claims.*.fields.*.form_field_id' => 'required|integer|exists:form_field,form_field_id',
            'claims.*.fields.*.field_value' => 'nullable|string',
        ]);

        // 고객 검증 (있으면)
        if (!empty($validated['customer_id'])) {
            Customer::where('agent_id', $agentId)
                ->where('customer_id', $validated['customer_id'])
                ->firstOrFail();
        }

        DB::beginTransaction();
        try {
            // 기존 하위 claims 삭제 후 재생성
            $existingClaims = $batch->claims()->get();
            foreach ($existingClaims as $existingClaim) {
                $existingClaim->fieldValues()->delete();
                $existingClaim->delete();
            }

            // 배치 정보 갱신
            $batch->update([
                'customer_id' => $validated['customer_id'] ?? $batch->customer_id,
                'total_count' => count($validated['claims']),
            ]);

            // 하위 claims 재생성
            foreach ($validated['claims'] as $claimData) {
                $claimForm = ClaimForm::findOrFail($claimData['claim_form_id']);

                $claimNumber = 'CLM-' . now()->format('Ymd') . '-' . substr((string) time(), -4) . str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);

                $claim = InsuranceClaim::create([
                    'customer_id' => $validated['customer_id'] ?? $batch->customer_id,
                    'batch_claim_id' => $batch->batch_claim_id,
                    'company_id' => $claimForm->company_id,
                    'agent_id' => $agentId,
                    'claim_form_id' => $claimForm->claim_form_id,
                    'claim_number' => $claimNumber,
                    'claim_type' => '대리청구',
                    'claim_status' => InsuranceClaim::STATUS_DRAFT,
                    'claim_date' => now()->toDateString(),
                    'created_by_id' => $agentId,
                ]);

                foreach ($claimData['fields'] as $fieldData) {
                    ClaimFieldValue::create([
                        'claim_id' => $claim->claim_id,
                        'form_field_id' => $fieldData['form_field_id'],
                        'field_value' => $fieldData['field_value'] ?? '',
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $batch->load([
                    'customer:customer_id,name,phone',
                    'claims' => function ($q) {
                        $q->with([
                            'claimForm:claim_form_id,form_name,company_id',
                            'claimForm.insuranceCompany:company_id,company_name',
                            'fieldValues.formField:form_field_id,field_label,field_type',
                        ]);
                    },
                ]),
                'message' => '배치 임시저장이 갱신되었습니다.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            $message = app()->isProduction()
                ? '배치 임시저장 갱신 중 오류가 발생했습니다.'
                : '배치 임시저장 갱신 중 오류가 발생했습니다: ' . $e->getMessage();

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 500);
        }
    }

    /**
     * 배치 임시저장 → 정식 제출
     */
    public function submitDraft(Request $request, int $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $batch = BatchClaim::where('agent_id', $agentId)
            ->where('batch_status', BatchClaim::STATUS_DRAFT)
            ->findOrFail($id);

        $request->validate([
            'customer_id' => 'nullable|string',
            'common_document_ids' => 'nullable|array',
            'common_document_ids.*' => 'integer',
        ]);

        // 고객 처리
        $customerId = $request->input('customer_id') ?? $batch->customer_id;
        if (empty($customerId)) {
            return response()->json([
                'success' => false,
                'message' => '다중 청구에는 고객 선택이 필수입니다.',
            ], 422);
        }

        $customer = Customer::where('agent_id', $agentId)
            ->where('customer_id', $customerId)
            ->firstOrFail();

        $claims = $batch->claims()->with('fieldValues')->get();

        if ($claims->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => '청구 건이 없습니다.',
            ], 422);
        }

        // 각 건 필수 필드 검증
        foreach ($claims as $claim) {
            $claimForm = ClaimForm::with('formFields')->findOrFail($claim->claim_form_id);
            $fieldValues = $claim->fieldValues;

            $requiredFields = $claimForm->formFields->where('is_required', true);
            $inputFieldIds = $fieldValues->pluck('form_field_id')->toArray();

            foreach ($requiredFields as $requiredField) {
                if (!in_array($requiredField->form_field_id, $inputFieldIds)) {
                    $companyName = $claimForm->insuranceCompany->company_name ?? '알 수 없음';
                    return response()->json([
                        'success' => false,
                        'message' => "[{$companyName}] {$requiredField->field_label} 필드는 필수입니다.",
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
                    $companyName = $claimForm->insuranceCompany->company_name ?? '알 수 없음';
                    return response()->json([
                        'success' => false,
                        'message' => "[{$companyName}] {$requiredField->field_label} 필드는 필수입니다.",
                    ], 422);
                }
            }
        }

        DB::beginTransaction();
        try {
            // 배치 상태 변경
            $batch->update([
                'customer_id' => $customer->customer_id,
                'batch_status' => BatchClaim::STATUS_PENDING,
            ]);

            // 각 건 제출 처리
            foreach ($claims as $claim) {
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

                // 공통 서류 복제
                if ($request->has('common_document_ids') && !empty($request->input('common_document_ids'))) {
                    $this->attachDocumentsToClaim($claim, $request->input('common_document_ids'), $agentId);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $batch->load([
                    'customer:customer_id,name,phone',
                    'claims' => function ($q) {
                        $q->with([
                            'claimForm:claim_form_id,form_name,company_id',
                            'claimForm.insuranceCompany:company_id,company_name,company_code',
                            'fieldValues.formField:form_field_id,field_label,field_type',
                            'documents',
                        ]);
                    },
                ]),
                'message' => $claims->count() . '건의 청구서가 제출되었습니다.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            $message = app()->isProduction()
                ? '배치 제출 중 오류가 발생했습니다.'
                : '배치 제출 중 오류가 발생했습니다: ' . $e->getMessage();

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 500);
        }
    }

    /**
     * 배치 임시저장 삭제
     */
    public function deleteDraft(Request $request, int $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $batch = BatchClaim::where('agent_id', $agentId)
            ->where('batch_status', BatchClaim::STATUS_DRAFT)
            ->findOrFail($id);

        DB::beginTransaction();
        try {
            // 하위 claims 및 fieldValues 삭제
            $claims = $batch->claims()->get();
            foreach ($claims as $claim) {
                $claim->fieldValues()->delete();
                $claim->delete();
            }

            $batch->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '배치 임시저장이 삭제되었습니다.',
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
     * 일괄 팩스 발송
     */
    public function sendFax(Request $request, int $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $batch = BatchClaim::where('agent_id', $agentId)
            ->findOrFail($id);

        $request->validate([
            'claim_ids' => 'nullable|array',
            'claim_ids.*' => 'integer',
        ]);

        $claimsQuery = $batch->claims();
        if ($request->has('claim_ids') && !empty($request->input('claim_ids'))) {
            $claimsQuery->whereIn('claim_id', $request->input('claim_ids'));
        }

        $claims = $claimsQuery->with(['documents', 'insuranceCompany'])->get();

        if ($claims->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => '발송할 청구 건이 없습니다.',
            ], 422);
        }

        $results = [];
        $sentCount = 0;

        foreach ($claims as $claim) {
            if (in_array($claim->fax_status, ['pending', 'sending'])) {
                $results[] = [
                    'claim_id' => $claim->claim_id,
                    'fax_status' => $claim->fax_status,
                    'message' => '팩스 발송이 진행 중입니다.',
                ];
                continue;
            }

            if (!$claim->generated_pdf_path) {
                $results[] = [
                    'claim_id' => $claim->claim_id,
                    'fax_status' => 'failed',
                    'message' => 'PDF가 생성되지 않았습니다.',
                ];
                continue;
            }

            try {
                // 첨부파일이 있으면 병합 PDF로 발송
                if ($claim->documents->isNotEmpty()) {
                    $mergedPdf = $this->pdfGenerator->mergeClaimWithAttachments($claim);
                    $result = $this->faxService->sendFaxWithContent($claim, $mergedPdf);
                } else {
                    $result = $this->faxService->sendFax($claim);
                }

                if ($result['success']) {
                    $claim->update([
                        'fax_status' => 'pending',
                        'fax_sent_at' => now(),
                        'fax_batch_id' => $result['reference_id'],
                        'fax_number_sent' => $result['fax_number'],
                    ]);

                    $results[] = [
                        'claim_id' => $claim->claim_id,
                        'fax_status' => 'pending',
                        'fax_number' => $result['fax_number'],
                    ];
                    $sentCount++;
                } else {
                    $claim->update(['fax_status' => 'failed']);
                    $results[] = [
                        'claim_id' => $claim->claim_id,
                        'fax_status' => 'failed',
                        'message' => $result['message'],
                    ];
                }
            } catch (\Exception $e) {
                $claim->update(['fax_status' => 'failed']);
                $results[] = [
                    'claim_id' => $claim->claim_id,
                    'fax_status' => 'failed',
                    'message' => app()->isProduction() ? '발송 중 오류 발생' : $e->getMessage(),
                ];
            }
        }

        $totalCount = count($results);
        $message = "{$totalCount}건 중 {$sentCount}건 발송 완료";

        return response()->json([
            'success' => $sentCount > 0,
            'data' => [
                'sent_count' => $sentCount,
                'results' => $results,
            ],
            'message' => $message,
        ]);
    }

    /**
     * 필수 필드 검증
     */
    private function validateRequiredFields(ClaimForm $claimForm, array $fields): ?string
    {
        $requiredFields = $claimForm->formFields->where('is_required', true);
        $inputFieldIds = collect($fields)->pluck('form_field_id')->toArray();

        foreach ($requiredFields as $requiredField) {
            if (!in_array($requiredField->form_field_id, $inputFieldIds)) {
                return "{$requiredField->field_label} 필드는 필수입니다.";
            }

            $inputField = collect($fields)->firstWhere('form_field_id', $requiredField->form_field_id);
            $fieldValue = $inputField['field_value'] ?? '';
            $isEmpty = match ($requiredField->field_type) {
                'checkbox' => empty($fieldValue) || $fieldValue === '[]',
                'consent' => $fieldValue !== 'agree',
                'signature' => !str_starts_with($fieldValue, 'data:image/'),
                default => empty(trim($fieldValue)),
            };
            if ($isEmpty) {
                return "{$requiredField->field_label} 필드는 필수입니다.";
            }
        }

        return null;
    }

    /**
     * 페이지별 이미지를 S3에 저장
     */
    private function savePageImages(InsuranceClaim $claim, array $imageBinaries): void
    {
        foreach ($imageBinaries as $imageData) {
            $s3Key = 'claims/' . $claim->claim_id . '/page_' . $imageData['page_number'] . '.jpg';
            Storage::disk('s3')->put($s3Key, $imageData['binary']);
        }
    }

    /**
     * 기존 문서 ID로 claim에 문서 레코드 복제 (S3 파일은 공유)
     */
    private function attachDocumentsToClaim(InsuranceClaim $claim, array $documentIds, string $agentId): void
    {
        foreach ($documentIds as $docId) {
            $sourceDoc = ClaimDocument::find($docId);
            if (!$sourceDoc) {
                continue;
            }

            // 이미 같은 claim에 같은 파일이 첨부되어 있으면 스킵
            $exists = ClaimDocument::where('claim_id', $claim->claim_id)
                ->where('document_file_url', $sourceDoc->document_file_url)
                ->exists();
            if ($exists) {
                continue;
            }

            ClaimDocument::create([
                'claim_id' => $claim->claim_id,
                'supporting_document_id' => $sourceDoc->supporting_document_id,
                'document_file_url' => $sourceDoc->document_file_url,
                'document_file_name' => $sourceDoc->document_file_name,
                'document_file_size' => $sourceDoc->document_file_size,
                'upload_status' => 'uploaded',
                'created_by_id' => $agentId,
            ]);
        }
    }
}
