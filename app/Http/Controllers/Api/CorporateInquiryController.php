<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CorporateInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CorporateInquiryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:100',
            'address' => 'nullable|string|max:255',
            'ceo_name' => 'required|string|max:50',
            'phone' => 'required|string|max:20',
            'annual_revenue' => 'nullable|string|max:20',
            'industry' => 'nullable|string|max:20',
            'consultation_field' => 'nullable|string|max:30',
            'privacy_agreed' => 'required|boolean',
        ]);

        $inquiry = CorporateInquiry::create(array_merge($validated, [
            'status' => 'NEW',
        ]));

        return response()->json([
            'success' => true,
            'message' => '문의가 접수되었습니다.',
            'data' => $inquiry,
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $query = CorporateInquiry::query()->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                  ->orWhere('ceo_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 20);
        $inquiries = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $inquiries,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $inquiry = CorporateInquiry::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $inquiry,
        ]);
    }
}
