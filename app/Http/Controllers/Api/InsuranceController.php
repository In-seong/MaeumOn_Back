<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Insurance;
use App\Models\InsuranceStatistics;
use App\Services\Insurance\InsuranceContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsuranceController extends Controller
{
    protected InsuranceContractService $contractService;

    public function __construct(InsuranceContractService $contractService)
    {
        $this->contractService = $contractService;
    }

    /**
     * 내 보험 목록 조회 (DB)
     * GET /api/insurances
     */
    public function index(Request $request): JsonResponse
    {
        $customerId = $request->user()->customer->customer_id;

        $query = Insurance::where('customer_id', $customerId)
            ->where('is_active', true)
            ->with(['company:company_id,company_name,company_code,logo_path']);

        // 계약 유형 필터
        if ($request->has('contract_type')) {
            $query->where('contract_type', $request->contract_type);
        }

        // 보험사 필터
        if ($request->has('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        // 계약 상태 필터
        if ($request->has('contract_status')) {
            $query->where('contract_status', $request->contract_status);
        }

        $insurances = $query->orderBy('subscription_date', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $insurances,
        ]);
    }

    /**
     * 보험 상세 + 보장내역
     * GET /api/insurances/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $customerId = $request->user()->customer->customer_id;

        $insurance = Insurance::where('customer_id', $customerId)
            ->with(['coverages', 'company', 'paymentHistories'])
            ->find($id);

        if (!$insurance) {
            return response()->json([
                'success' => false,
                'message' => '보험 정보를 찾을 수 없습니다.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $insurance,
        ]);
    }

    /**
     * 분석통계 조회
     * GET /api/insurances/statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $customerId = $request->user()->customer->customer_id;

        $query = InsuranceStatistics::where('customer_id', $customerId);

        // 통계 유형 필터
        if ($request->has('stat_type')) {
            $query->where('stat_type', $request->stat_type);
        }

        $statistics = $query->orderBy('stat_type')
            ->orderBy('coverage_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $statistics,
        ]);
    }
}
