<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BatchClaim;
use Illuminate\Http\Request;

class AdminBatchClaimController extends Controller
{
    public function index(Request $request)
    {
        $query = BatchClaim::with(['customer', 'agent'])
            ->withCount('claims');

        // 상태 필터
        if ($request->filled('batch_status')) {
            $query->where('batch_status', $request->batch_status);
        }

        // 검색 (고객명, 설계사명)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('customer', function ($cq) use ($search) {
                    $cq->where('name', 'like', "%{$search}%");
                })->orWhereHas('agent', function ($aq) use ($search) {
                    $aq->where('name', 'like', "%{$search}%");
                });
            });
        }

        $query->orderBy('created_at', 'desc');

        $perPage = $request->input('per_page', 15);

        return response()->json([
            'success' => true,
            'data' => $query->paginate($perPage),
        ]);
    }
}
