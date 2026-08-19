<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminBranchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $admin = Admin::where('account_id', $request->user()->account_id)->first();

        if (!$admin || !$admin->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => '슈퍼 관리자만 접근 가능합니다.'], 403);
        }

        $query = Branch::query()
            ->withCount('agents')
            ->with('manager:admin_id,name,phone');

        if ($request->has('is_active') && $request->is_active !== 'all') {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('branch_name', 'like', "%{$search}%")
                  ->orWhere('branch_code', 'like', "%{$search}%")
                  ->orWhere('region', 'like', "%{$search}%");
            });
        }

        $branches = $query->orderBy('branch_name')->get();

        return response()->json([
            'success' => true,
            'data' => $branches,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $admin = Admin::where('account_id', $request->user()->account_id)->first();

        if (!$admin || !$admin->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => '슈퍼 관리자만 접근 가능합니다.'], 403);
        }

        $validated = $request->validate([
            'branch_name' => 'required|string|max:50|unique:branch,branch_name',
            'branch_code' => 'required|string|max:10|unique:branch,branch_code',
            'region' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'manager_admin_id' => 'nullable|string|exists:admin,admin_id',
        ]);

        $branch = Branch::create($validated);

        return response()->json([
            'success' => true,
            'data' => $branch->load('manager:admin_id,name,phone'),
            'message' => '지사가 생성되었습니다.',
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $admin = Admin::where('account_id', $request->user()->account_id)->first();

        if (!$admin || !$admin->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => '슈퍼 관리자만 접근 가능합니다.'], 403);
        }

        $branch = Branch::with(['manager:admin_id,name,phone'])
            ->withCount('agents')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $branch,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $admin = Admin::where('account_id', $request->user()->account_id)->first();

        if (!$admin || !$admin->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => '슈퍼 관리자만 접근 가능합니다.'], 403);
        }

        $branch = Branch::findOrFail($id);

        $validated = $request->validate([
            'branch_name' => 'sometimes|string|max:50|unique:branch,branch_name,' . $id . ',branch_id',
            'branch_code' => 'sometimes|string|max:10|unique:branch,branch_code,' . $id . ',branch_id',
            'region' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'manager_admin_id' => 'nullable|string|exists:admin,admin_id',
            'is_active' => 'sometimes|boolean',
        ]);

        $branch->update($validated);

        return response()->json([
            'success' => true,
            'data' => $branch->fresh()->load('manager:admin_id,name,phone'),
            'message' => '지사 정보가 수정되었습니다.',
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $admin = Admin::where('account_id', $request->user()->account_id)->first();

        if (!$admin || !$admin->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => '슈퍼 관리자만 접근 가능합니다.'], 403);
        }

        $branch = Branch::withCount('agents')->findOrFail($id);

        if ($branch->agents_count > 0) {
            return response()->json([
                'success' => false,
                'message' => "소속 설계사 {$branch->agents_count}명이 있습니다. 설계사를 먼저 이동 또는 삭제해주세요.",
                'data' => ['agents_count' => $branch->agents_count],
            ], 422);
        }

        DB::transaction(function () use ($branch) {
            Admin::where('branch_id', $branch->branch_id)->update(['branch_id' => null, 'admin_role' => 'SUPER']);
            $branch->delete();
        });

        return response()->json([
            'success' => true,
            'message' => '지사가 삭제되었습니다.',
        ]);
    }

    /**
     * 지사 소속 설계사 일괄 이동/삭제 (지사 삭제 전 처리용)
     */
    public function transferAgents(Request $request, int $id): JsonResponse
    {
        $admin = Admin::where('account_id', $request->user()->account_id)->first();

        if (!$admin || !$admin->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => '슈퍼 관리자만 접근 가능합니다.'], 403);
        }

        $branch = Branch::findOrFail($id);

        $validated = $request->validate([
            'action' => 'required|in:transfer,remove',
            'target_branch_id' => 'required_if:action,transfer|nullable|integer|exists:branch,branch_id',
            'agent_ids' => 'required|array|min:1',
            'agent_ids.*' => 'string|exists:agent,agent_id',
        ]);

        DB::transaction(function () use ($branch, $validated) {
            if ($validated['action'] === 'transfer') {
                DB::table('agent_branch')
                    ->where('branch_id', $branch->branch_id)
                    ->whereIn('agent_id', $validated['agent_ids'])
                    ->update(['branch_id' => $validated['target_branch_id']]);
            } else {
                DB::table('agent_branch')
                    ->where('branch_id', $branch->branch_id)
                    ->whereIn('agent_id', $validated['agent_ids'])
                    ->delete();
            }
        });

        $actionLabel = $validated['action'] === 'transfer' ? '이동' : '해제';

        return response()->json([
            'success' => true,
            'message' => "설계사 {$actionLabel}가 완료되었습니다.",
        ]);
    }

    /**
     * 드롭다운용 지사 목록 (모든 관리자 접근 가능)
     */
    public function dropdown(Request $request): JsonResponse
    {
        $branches = Branch::where('is_active', true)
            ->orderBy('branch_name')
            ->get(['branch_id', 'branch_name', 'branch_code']);

        return response()->json([
            'success' => true,
            'data' => $branches,
        ]);
    }
}
