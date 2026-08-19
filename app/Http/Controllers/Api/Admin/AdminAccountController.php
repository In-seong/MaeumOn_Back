<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $currentAdmin = Admin::where('account_id', $request->user()->account_id)->first();

        if (!$currentAdmin || !$currentAdmin->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => '슈퍼 관리자만 접근 가능합니다.'], 403);
        }

        $query = Admin::query()
            ->with(['account:account_id,username,is_active', 'branch:branch_id,branch_name']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('admin_role') && in_array($request->admin_role, ['SUPER', 'BRANCH'])) {
            $query->where('admin_role', $request->admin_role);
        }

        $admins = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $admins,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $currentAdmin = Admin::where('account_id', $request->user()->account_id)->first();

        if (!$currentAdmin || !$currentAdmin->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => '슈퍼 관리자만 접근 가능합니다.'], 403);
        }

        $validated = $request->validate([
            'username' => 'required|string|max:50|unique:account,username',
            'password' => 'required|string|min:6|max:100',
            'name' => 'required|string|max:50',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'department' => 'nullable|string|max:50',
            'position' => 'nullable|string|max:50',
            'admin_role' => 'required|in:SUPER,BRANCH',
            'branch_id' => 'required_if:admin_role,BRANCH|nullable|integer|exists:branch,branch_id',
        ]);

        if ($validated['admin_role'] === 'SUPER' && !empty($validated['branch_id'])) {
            $validated['branch_id'] = null;
        }

        if (!empty($validated['phone'])) {
            $validated['phone'] = preg_replace('/\D/', '', $validated['phone']);
        }

        $admin = DB::transaction(function () use ($validated) {
            $account = Account::create([
                'username' => $validated['username'],
                'password_hash' => Hash::make($validated['password']),
                'role' => Account::ROLE_ADMIN,
                'is_active' => true,
            ]);

            $lastAdmin = Admin::where('admin_id', 'like', 'AD%')
                ->orderByRaw('CAST(SUBSTRING(admin_id, 3) AS UNSIGNED) DESC')
                ->first();
            $nextNum = $lastAdmin
                ? (int) substr($lastAdmin->admin_id, 2) + 1
                : 1;
            $adminId = 'AD' . str_pad((string) $nextNum, 6, '0', STR_PAD_LEFT);

            return Admin::create([
                'admin_id' => $adminId,
                'account_id' => $account->account_id,
                'name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'department' => $validated['department'] ?? null,
                'position' => $validated['position'] ?? null,
                'admin_role' => $validated['admin_role'],
                'branch_id' => $validated['branch_id'] ?? null,
                'is_active' => true,
            ]);
        });

        return response()->json([
            'success' => true,
            'data' => $admin->load(['account:account_id,username,is_active', 'branch:branch_id,branch_name']),
            'message' => '관리자 계정이 생성되었습니다.',
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $currentAdmin = Admin::where('account_id', $request->user()->account_id)->first();

        if (!$currentAdmin || !$currentAdmin->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => '슈퍼 관리자만 접근 가능합니다.'], 403);
        }

        $admin = Admin::where('admin_id', $id)->firstOrFail();

        $validated = $request->validate([
            'username' => 'sometimes|string|max:50|unique:account,username,' . $admin->account_id . ',account_id',
            'password' => 'sometimes|string|min:6|max:100',
            'name' => 'sometimes|string|max:50',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'department' => 'nullable|string|max:50',
            'position' => 'nullable|string|max:50',
            'admin_role' => 'sometimes|in:SUPER,BRANCH',
            'branch_id' => 'nullable|integer|exists:branch,branch_id',
        ]);

        if (!empty($validated['phone'])) {
            $validated['phone'] = preg_replace('/\D/', '', $validated['phone']);
        }

        $role = $validated['admin_role'] ?? $admin->admin_role;
        if ($role === 'SUPER') {
            $validated['branch_id'] = null;
        }

        DB::transaction(function () use ($admin, $validated) {
            if (isset($validated['username']) || isset($validated['password'])) {
                $accountUpdate = [];
                if (isset($validated['username'])) {
                    $accountUpdate['username'] = $validated['username'];
                }
                if (isset($validated['password'])) {
                    $accountUpdate['password_hash'] = Hash::make($validated['password']);
                }
                Account::where('account_id', $admin->account_id)->update($accountUpdate);
            }

            $adminData = array_diff_key($validated, array_flip(['username', 'password']));
            if (!empty($adminData)) {
                $admin->update($adminData);
            }
        });

        return response()->json([
            'success' => true,
            'data' => $admin->fresh()->load(['account:account_id,username,is_active', 'branch:branch_id,branch_name']),
            'message' => '관리자 정보가 수정되었습니다.',
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $currentAdmin = Admin::where('account_id', $request->user()->account_id)->first();

        if (!$currentAdmin || !$currentAdmin->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => '슈퍼 관리자만 접근 가능합니다.'], 403);
        }

        $admin = Admin::where('admin_id', $id)->firstOrFail();

        if ($admin->admin_id === $currentAdmin->admin_id) {
            return response()->json(['success' => false, 'message' => '자기 자신은 삭제할 수 없습니다.'], 422);
        }

        DB::transaction(function () use ($admin) {
            $admin->update(['is_active' => false]);
            if ($admin->account_id) {
                Account::where('account_id', $admin->account_id)->update(['is_active' => false]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => '관리자 계정이 비활성화되었습니다.',
        ]);
    }
}
