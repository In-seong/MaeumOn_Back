<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\InsuranceCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsuranceCompanyController extends Controller
{
    /**
     * 공개 API: 활성화된 보험사 목록
     */
    public function publicIndex(Request $request): JsonResponse
    {
        $companies = InsuranceCompany::active()
            ->select('company_id', 'company_name', 'company_code', 'logo_path')
            ->orderBy('company_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $companies,
        ]);
    }

    /**
     * 관리자: 보험사 목록 (페이지네이션)
     */
    public function index(Request $request): JsonResponse
    {
        $query = InsuranceCompany::query();

        // 검색
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                  ->orWhere('company_code', 'like', "%{$search}%");
            });
        }

        // 활성화 필터
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $companies = $query->withCount('claimForms')
            ->orderBy('company_name')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $companies,
        ]);
    }

    /**
     * 관리자: 보험사 등록
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:100',
            'company_code' => 'nullable|string|max:20|unique:insurance_company,company_code',
            'business_number' => 'nullable|string|max:20',
            'representative_name' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'fax_number' => 'required|string|max:20',
            'logo_path' => 'nullable|string|max:255',
            'website_url' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $company = InsuranceCompany::create($validated);

        return response()->json([
            'success' => true,
            'data' => $company,
            'message' => '보험사가 등록되었습니다.',
        ], 201);
    }

    /**
     * 관리자: 보험사 상세
     */
    public function show(int $id): JsonResponse
    {
        $company = InsuranceCompany::with(['claimForms' => function ($query) {
            $query->withCount('formFields');
        }])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $company,
        ]);
    }

    /**
     * 관리자: 보험사 수정
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $company = InsuranceCompany::findOrFail($id);

        $validated = $request->validate([
            'company_name' => 'sometimes|required|string|max:100',
            'company_code' => 'nullable|string|max:20|unique:insurance_company,company_code,' . $id . ',company_id',
            'business_number' => 'nullable|string|max:20',
            'representative_name' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'fax_number' => 'sometimes|required|string|max:20',
            'logo_path' => 'nullable|string|max:255',
            'website_url' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $company->update($validated);

        return response()->json([
            'success' => true,
            'data' => $company,
            'message' => '보험사 정보가 수정되었습니다.',
        ]);
    }

    /**
     * 관리자: 보험사 삭제
     */
    public function destroy(int $id): JsonResponse
    {
        $company = InsuranceCompany::findOrFail($id);

        // 연관된 양식이 있는지 확인
        if ($company->claimForms()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => '이 보험사에 연결된 양식이 있어 삭제할 수 없습니다.',
            ], 400);
        }

        $company->delete();

        return response()->json([
            'success' => true,
            'message' => '보험사가 삭제되었습니다.',
        ]);
    }
}
