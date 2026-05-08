<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Services\ConsultationNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerConsultationController extends Controller
{
    public function __construct(private ConsultationNotifier $notifier)
    {
    }

    /**
     * 고객 본인의 상담 요청 목록
     */
    public function index(Request $request): JsonResponse
    {
        $customerId = $request->user()->customer->customer_id;

        $query = Consultation::where('customer_id', $customerId);

        if ($request->filled('status')) {
            $query->where('consultation_status', $request->status);
        }
        if ($request->filled('consultation_type')) {
            $query->where('consultation_type', $request->consultation_type);
        }

        $perPage = min(max((int) $request->get('per_page', 15), 1), 100);
        $consultations = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $consultations,
        ]);
    }

    /**
     * 본인 상담 상세
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $customerId = $request->user()->customer->customer_id;

        $consultation = Consultation::where('customer_id', $customerId)->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $consultation,
        ]);
    }

    /**
     * 상담 요청 생성 (SFR-008, SFR-017)
     * 담당 설계사가 있으면 그에게 배정, 없으면 관리자에게 배정.
     */
    public function store(Request $request): JsonResponse
    {
        $customer = $request->user()->customer;

        $validated = $request->validate([
            'consultation_type'    => 'required|string|max:50',
            'consultation_content' => 'required|string',
        ]);

        $assigneeId   = $customer->agent_id ?: null;
        $assigneeType = $customer->agent_id ? 'AGENT' : 'ADMIN';

        $consultation = DB::transaction(function () use ($customer, $validated, $assigneeId, $assigneeType) {
            return Consultation::create([
                'customer_id'          => $customer->customer_id,
                'assignee_id'          => $assigneeId,
                'assignee_type'        => $assigneeType,
                'consultation_type'    => $validated['consultation_type'],
                'consultation_date'    => now(),
                'consultation_content' => $validated['consultation_content'],
                'consultation_status'  => 'pending',
                'customer_name'        => $customer->name,
                'customer_phone'       => $customer->phone,
                'created_by_id'        => $customer->customer_id,
            ]);
        });

        $this->notifier->onRequested($consultation, $customer);

        return response()->json([
            'success' => true,
            'data'    => $consultation,
            'message' => '상담 요청이 접수되었습니다.',
        ], 201);
    }
}
