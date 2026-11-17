<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\ConsentTemplate;
use App\Models\Customer;
use App\Models\UnclaimedConsent;
use App\Models\UnclaimedResult;
use App\Models\UnclaimedSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AgentUnclaimedController extends Controller
{
    /**
     * GET /api/agent/unclaimed/sessions
     */
    public function index(Request $request): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $query = UnclaimedSession::where('agent_id', $agentId)
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        $sessions = $query->paginate($request->input('per_page', 20));

        return response()->json(['success' => true, 'data' => $sessions]);
    }

    /**
     * POST /api/agent/unclaimed/sessions
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'nullable|string|exists:customer,customer_id',
            'customer_name' => 'required|string|max:50',
            'customer_phone' => 'required|string|max:20',
            'customer_birth_date' => 'required|date',
            'customer_resident_number' => 'nullable|string|max:20',
            'customer_address' => 'nullable|string|max:255',
            'customer_detailed_address' => 'nullable|string|max:255',
            'customer_gender' => 'nullable|in:M,F,OTHER',
            'customer_telecom' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $agentId = $request->user()->agent->agent_id;

        if ($request->filled('customer_id')) {
            $customer = Customer::where('customer_id', $request->customer_id)
                ->where('agent_id', $agentId)
                ->first();

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => '해당 고객을 찾을 수 없습니다.',
                ], 404);
            }
        }

        $session = UnclaimedSession::create([
            'agent_id' => $agentId,
            'customer_id' => $request->customer_id,
            'customer_name' => $request->customer_name,
            'customer_phone' => $request->customer_phone,
            'customer_birth_date' => $request->customer_birth_date,
            'customer_resident_number' => $request->customer_resident_number,
            'customer_address' => $request->customer_address,
            'customer_detailed_address' => $request->customer_detailed_address,
            'customer_gender' => $request->customer_gender,
            'customer_telecom' => $request->customer_telecom,
            'status' => 'consent',
        ]);

        return response()->json([
            'success' => true,
            'data' => $session,
            'message' => '세션이 생성되었습니다.',
        ], 201);
    }

    /**
     * GET /api/agent/unclaimed/sessions/{sessionId}
     */
    public function show(Request $request, int $sessionId): JsonResponse
    {
        $session = $this->resolveSession($request, $sessionId);
        if ($session instanceof JsonResponse) return $session;

        $session->load(['results', 'consents.template']);

        return response()->json(['success' => true, 'data' => $session]);
    }

    /**
     * GET /api/agent/unclaimed/consent-templates
     */
    public function consentTemplates(): JsonResponse
    {
        $templates = ConsentTemplate::where('is_active', true)
            ->whereIn('consent_type', [
                ConsentTemplate::TYPE_UNCLAIMED_SENSITIVE,
                ConsentTemplate::TYPE_UNCLAIMED_PERSONAL,
                ConsentTemplate::TYPE_UNCLAIMED_THIRD_PARTY,
                ConsentTemplate::TYPE_UNCLAIMED_TERMS,
                ConsentTemplate::TYPE_UNCLAIMED_MEDICAL_RECORD,
            ])
            ->orderBy('consent_template_id')
            ->get();

        return response()->json(['success' => true, 'data' => $templates]);
    }

    /**
     * POST /api/agent/unclaimed/sessions/{sessionId}/consents
     */
    public function submitConsents(Request $request, int $sessionId): JsonResponse
    {
        $session = $this->resolveSession($request, $sessionId);
        if ($session instanceof JsonResponse) return $session;

        $validator = Validator::make($request->all(), [
            'consent_template_ids' => 'required|array|min:1',
            'consent_template_ids.*' => 'integer|exists:consent_template,consent_template_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $now = now();
        $ip = $request->ip();

        foreach ($request->consent_template_ids as $templateId) {
            UnclaimedConsent::updateOrCreate(
                [
                    'session_id' => $session->session_id,
                    'consent_template_id' => $templateId,
                ],
                [
                    'agreed_at' => $now,
                    'ip_address' => $ip,
                ]
            );
        }

        $session->update(['status' => 'identity_verify']);

        return response()->json([
            'success' => true,
            'data' => $session->fresh()->load('consents.template'),
            'message' => '동의서가 제출되었습니다.',
        ]);
    }

    /**
     * POST /api/agent/unclaimed/sessions/{sessionId}/id-card
     */
    public function uploadIdCard(Request $request, int $sessionId): JsonResponse
    {
        $session = $this->resolveSession($request, $sessionId);
        if ($session instanceof JsonResponse) return $session;

        $validator = Validator::make($request->all(), [
            'id_card' => 'required|image|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        if ($session->id_card_path) {
            Storage::disk('s3')->delete($session->id_card_path);
        }

        $path = $request->file('id_card')->store(
            "unclaimed/{$session->session_id}/id-card",
            's3'
        );

        $session->update([
            'id_card_path' => $path,
            'status' => 'signature',
        ]);

        return response()->json([
            'success' => true,
            'data' => $session->fresh(),
            'message' => '신분증이 업로드되었습니다.',
        ]);
    }

    /**
     * POST /api/agent/unclaimed/sessions/{sessionId}/signature
     */
    public function uploadSignature(Request $request, int $sessionId): JsonResponse
    {
        $session = $this->resolveSession($request, $sessionId);
        if ($session instanceof JsonResponse) return $session;

        $validator = Validator::make($request->all(), [
            'signature' => 'required|file|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        if ($session->signature_path) {
            Storage::disk('s3')->delete($session->signature_path);
        }

        $path = $request->file('signature')->store(
            "unclaimed/{$session->session_id}/signature",
            's3'
        );

        $session->update(['signature_path' => $path]);

        return response()->json([
            'success' => true,
            'data' => $session->fresh(),
            'message' => '전자서명이 저장되었습니다.',
        ]);
    }

    /**
     * POST /api/agent/unclaimed/sessions/{sessionId}/fetch
     * CODEF 2-Way 인증 시작 (심평원 진료내역 + 홈택스)
     */
    public function startFetch(Request $request, int $sessionId): JsonResponse
    {
        $session = $this->resolveSession($request, $sessionId);
        if ($session instanceof JsonResponse) return $session;

        $validator = Validator::make($request->all(), [
            'loginTypeLevel' => 'required|string',
            'telecom' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $session->update(['status' => 'fetching']);

        // TODO: CODEF API 호출 (심평원 진료내역 조회 + 홈택스 연말정산간소화)
        // 기존 AgentCodefController의 fetchMedical 패턴 참고
        // 현재는 2-Way 인증 시작 stub

        return response()->json([
            'success' => true,
            'data' => [
                'session_id' => $session->session_id,
                'status' => 'fetching',
                'requires_2way' => true,
            ],
            'message' => '본인인증을 진행해주세요.',
        ]);
    }

    /**
     * POST /api/agent/unclaimed/sessions/{sessionId}/fetch/confirm
     * 2-Way 인증 확인
     */
    public function confirmFetch(Request $request, int $sessionId): JsonResponse
    {
        $session = $this->resolveSession($request, $sessionId);
        if ($session instanceof JsonResponse) return $session;

        // TODO: CODEF 2-Way 인증 확인 후 데이터 수신
        // 기존 AgentCodefController의 confirmMedical 패턴 참고

        $session->update(['status' => 'analyzing']);

        return response()->json([
            'success' => true,
            'data' => $session->fresh(),
            'message' => '인증이 완료되었습니다. 분석을 시작합니다.',
        ]);
    }

    /**
     * POST /api/agent/unclaimed/sessions/{sessionId}/analyze
     * 진료내역 vs 보험계약 매칭 분석
     */
    public function analyze(Request $request, int $sessionId): JsonResponse
    {
        $session = $this->resolveSession($request, $sessionId);
        if ($session instanceof JsonResponse) return $session;

        $session->update(['status' => 'analyzing']);

        try {
            $results = $this->performAnalysis($session);

            $totalAmount = collect($results)->sum('estimated_claim_amount');

            $session->update([
                'status' => 'completed',
                'total_unclaimed_amount' => $totalAmount,
                'analyzed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $session->fresh()->load('results'),
                'message' => '분석이 완료되었습니다.',
            ]);
        } catch (\Exception $e) {
            Log::error('숨은보험금 분석 실패', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '분석 중 오류가 발생했습니다: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/agent/unclaimed/sessions/{sessionId}/results
     */
    public function results(Request $request, int $sessionId): JsonResponse
    {
        $session = $this->resolveSession($request, $sessionId);
        if ($session instanceof JsonResponse) return $session;

        $results = UnclaimedResult::where('session_id', $sessionId)
            ->orderBy('visit_date', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $results]);
    }

    /**
     * POST /api/agent/unclaimed/sessions/{sessionId}/cancel
     */
    public function cancel(Request $request, int $sessionId): JsonResponse
    {
        $session = $this->resolveSession($request, $sessionId);
        if ($session instanceof JsonResponse) return $session;

        if ($session->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => '이미 완료된 세션은 취소할 수 없습니다.',
            ], 422);
        }

        $session->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'data' => $session->fresh(),
            'message' => '세션이 취소되었습니다.',
        ]);
    }

    /**
     * 세션 조회 + 소유권 검증
     */
    private function resolveSession(Request $request, int $sessionId): UnclaimedSession|JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $session = UnclaimedSession::where('session_id', $sessionId)
            ->where('agent_id', $agentId)
            ->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => '세션을 찾을 수 없습니다.',
            ], 404);
        }

        return $session;
    }

    /**
     * 진료내역 vs 보험계약 매칭 분석
     * 해당 고객의 진료내역(medical_record)과 보험계약(insurance)을 비교하여
     * 미청구 건을 식별합니다.
     */
    private function performAnalysis(UnclaimedSession $session): array
    {
        $customerId = $session->customer_id;
        if (!$customerId) {
            return [];
        }

        $medicalRecords = \App\Models\MedicalRecord::where('customer_id', $customerId)
            ->orderBy('treatment_start_date', 'desc')
            ->get();

        $insurances = \App\Models\Insurance::where('customer_id', $customerId)
            ->where('contract_status', 'active')
            ->get();

        if ($medicalRecords->isEmpty() || $insurances->isEmpty()) {
            return [];
        }

        $results = [];

        foreach ($medicalRecords as $record) {
            $hasExistingClaim = \App\Models\InsuranceClaim::where('customer_id', $customerId)
                ->where('accident_date', $record->treatment_start_date)
                ->exists();

            if ($hasExistingClaim) {
                continue;
            }

            foreach ($insurances as $insurance) {
                $estimatedAmount = $this->estimateClaimAmount($record, $insurance);
                if ($estimatedAmount <= 0) {
                    continue;
                }

                $result = UnclaimedResult::create([
                    'session_id' => $session->session_id,
                    'medical_record_id' => $record->medical_record_id ?? null,
                    'insurance_id' => $insurance->insurance_id,
                    'visit_date' => $record->treatment_start_date,
                    'hospital_name' => $record->hospital_name ?? $record->institution_name ?? '',
                    'diagnosis' => $record->diagnosis_name ?? '',
                    'treatment_type' => $record->treatment_type ?? '통원',
                    'total_cost' => $record->own_charge_total ?? 0,
                    'estimated_claim_amount' => $estimatedAmount,
                    'matched_insurance_product' => $insurance->product_name ?? '',
                    'matched_coverage' => $insurance->coverage_type ?? '',
                    'claim_status' => 'unclaimed',
                ]);

                $results[] = $result;
                break;
            }
        }

        return $results;
    }

    /**
     * 청구 가능 금액 추정 (기본 로직)
     */
    private function estimateClaimAmount($record, $insurance): float
    {
        $ownCharge = (float) ($record->own_charge_total ?? 0);
        if ($ownCharge <= 0) {
            return 0;
        }

        $treatmentType = $record->treatment_type ?? '';

        if (str_contains($treatmentType, '입원')) {
            return $ownCharge * 0.8;
        }

        if ($ownCharge >= 10000) {
            return $ownCharge * 0.7;
        }

        return 0;
    }
}
